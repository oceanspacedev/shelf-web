<?php

namespace App\Console\Commands;

use App\Models\CustomAssetAttribute;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledNotifications extends Command
{
    protected $signature = 'notifications:send-scheduled';

    protected $description = 'Mengirim notifikasi terjadwal berdasarkan konfigurasi CustomAssetAttribute';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Ambil semua CustomAssetAttributes yang dapat diberi notifikasi
        $attributes = CustomAssetAttribute::where('is_notifiable', true)->get();

        foreach ($attributes as $attribute) {
            if ($attribute->notification_type === 'relative_date' && $attribute->notification_offset) {
                $this->handleRelativeDateNotification($attribute);
            }

            if ($attribute->notification_type === 'fixed_date' && $attribute->fixed_notification_date) {
                $this->handleFixedDateNotification($attribute);
            }
        }

        $this->info('Proses pengiriman notifikasi terjadwal selesai.');
    }

    protected function handleRelativeDateNotification($attribute)
    {
        $assetAttributes = $attribute->assetAttributes()
            ->whereNotNull('attribute_value')
            ->with(['asset.assetLocation'])
            ->get();

        $today = CarbonImmutable::now();

        foreach ($assetAttributes as $assetAttribute) {
            if (! $assetAttribute->shouldSendExpiryReminderOn($today)) {
                continue;
            }

            $assetName = $assetAttribute->asset->name ?? 'N/A';
            $locationName = $assetAttribute->asset->assetLocation->name ?? 'N/A';
            $expiryDate = $assetAttribute->expiryDate()?->format('d M Y') ?? '-';
            $status = $assetAttribute->expiryReminderStatusLabelOn($today);
            $documentNumber = $assetAttribute->documentNumber();
            $documentUrl = $assetAttribute->documentUrl();

            $message = "🔔 *Pengingat Dokumen Aset* 🔔\n\n";
            $message .= "Dokumen aset membutuhkan pembaruan.\n\n";
            $message .= "📦 *Nama Aset*: {$assetName}\n";
            $message .= "🔖 *Dokumen*: {$attribute->name}\n";
            $message .= "📅 *Berlaku Sampai*: {$expiryDate}\n";
            $message .= "⚠️ *Status*: {$status}\n";
            $message .= "📍 *Lokasi*: {$locationName}\n";

            if ($documentNumber) {
                $message .= "🧾 *Nomor Dokumen*: {$documentNumber}\n";
            }

            if ($documentUrl) {
                $message .= "📎 *Lampiran*: {$documentUrl}\n";
            }

            $message .= "\nPengingat ini akan muncul setiap hari sampai dokumen diperbarui dengan tanggal berlaku dan lampiran baru.\n\n";
            $message .= '— Bot';

            $this->sendNotification($message, $assetAttribute->asset);
        }

        unset($assetAttributes);
    }

    protected function handleFixedDateNotification($attribute)
    {
        $fixedDate = CarbonImmutable::parse($attribute->fixed_notification_date);

        if (CarbonImmutable::now()->isSameDay($fixedDate)) {
            // Mengirim notifikasi
            $message = "🔔 *Notifikasi Atribut Tetap* 🔔\n\n";
            $message .= "Atribut {$attribute->name} memiliki notifikasi pada tanggal tetap.\n\n—";
            $this->sendNotification($message, null);
        }
    }

    protected function sendNotification($message, $asset)
    {
        // Contoh pengiriman pesan via WhatsApp menggunakan Guzzle Client
        $client = new Client;
        $apiEndpoint = env('WHATSAPP_API_ENDPOINT');
        $phoneNumber = env('DEFAULT_NOTIFICATION_PHONE');

        if (! filled($apiEndpoint) || ! filled($phoneNumber)) {
            Log::warning('Konfigurasi WhatsApp notifikasi aset belum lengkap.', [
                'asset_id' => $asset?->id,
            ]);

            return;
        }

        try {
            $response = $client->post($apiEndpoint, [
                'query' => [
                    'apikey' => env('WHATSAPP_API_KEY'),
                    'sender' => env('WHATSAPP_SENDER_NUMBER'),
                    'receiver' => $phoneNumber,
                    'message' => $message,
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('Gagal mengirim pesan WhatsApp: '.$response->getBody());
            }
        } catch (\Exception $e) {
            Log::error('Gagal mengirim pesan WhatsApp: '.$e->getMessage());
        }
    }
}
