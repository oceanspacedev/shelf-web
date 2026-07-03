<?php

namespace App\Console\Commands;

use App\Models\CustomAssetAttribute;
use App\Models\User;
use App\Services\WhatsappService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

            $this->sendNotification(
                $message,
                $assetAttribute->asset,
                $attribute,
                "Pengingat {$attribute->name} - {$assetName}"
            );
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
            $this->sendNotification($message, null, $attribute, "Notifikasi {$attribute->name}");
        }
    }

    protected function sendNotification(string $message, $asset, CustomAssetAttribute $attribute, string $subject): void
    {
        $hasRecipient = false;

        if ($attribute->usesNotificationChannel(CustomAssetAttribute::CHANNEL_WHATSAPP)) {
            foreach ($this->resolveWhatsappRecipients($attribute) as $phoneNumber) {
                $hasRecipient = true;
                $lockKey = $this->dailyDispatchLockKey($attribute, $asset, CustomAssetAttribute::CHANNEL_WHATSAPP, $phoneNumber);

                if (! $this->acquireDailyDispatchLock($lockKey)) {
                    continue;
                }

                if (! $this->sendWhatsappNotification($message, $asset, $phoneNumber)) {
                    Cache::forget($lockKey);
                }
            }
        }

        if ($attribute->usesNotificationChannel(CustomAssetAttribute::CHANNEL_EMAIL)) {
            foreach ($this->resolveEmailRecipients($attribute) as $email) {
                $hasRecipient = true;
                $lockKey = $this->dailyDispatchLockKey($attribute, $asset, CustomAssetAttribute::CHANNEL_EMAIL, $email);

                if (! $this->acquireDailyDispatchLock($lockKey)) {
                    continue;
                }

                if (! $this->sendEmailNotification($subject, $message, $asset, $email)) {
                    Cache::forget($lockKey);
                }
            }
        }

        if (! $hasRecipient) {
            Log::warning('Tidak ada penerima notifikasi aset yang valid.', [
                'custom_attribute_id' => $attribute->id,
                'asset_id' => $asset?->id,
                'channels' => $attribute->notificationChannels(),
            ]);
        }
    }

    protected function resolveWhatsappRecipients(CustomAssetAttribute $attribute): array
    {
        $recipients = $this->recipientUsers($attribute)
            ->pluck('whatsapp_number')
            ->merge($attribute->notificationRecipientWhatsappNumbers())
            ->map(fn ($recipient) => WhatsappService::normalizeNumber($recipient))
            ->filter()
            ->values()
            ->all();

        if ($recipients === [] && filled(config('services.whatsapp_gateway.default_target'))) {
            $recipients[] = WhatsappService::normalizeNumber(config('services.whatsapp_gateway.default_target'));
        }

        return collect($recipients)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function recipientUsers(CustomAssetAttribute $attribute): Collection
    {
        $userIds = $attribute->notificationRecipientUserIds();

        if ($userIds === []) {
            return collect();
        }

        return User::query()
            ->whereKey($userIds)
            ->get(['id', 'name', 'email', 'whatsapp_number']);
    }

    protected function resolveEmailRecipients(CustomAssetAttribute $attribute): array
    {
        return $this->recipientUsers($attribute)
            ->pluck('email')
            ->merge($attribute->notificationRecipientEmails())
            ->map(fn ($email) => is_string($email) ? trim($email) : null)
            ->filter(fn ($email) => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    protected function sendWhatsappNotification(string $message, $asset, string $phoneNumber): bool
    {
        $sent = WhatsappService::send($phoneNumber, $message);

        if (! $sent) {
            Log::error('Gagal mengirim pesan WhatsApp pengingat aset.', [
                'asset_id' => $asset?->id,
                'receiver' => $phoneNumber,
            ]);
        }

        return $sent;
    }

    protected function dailyDispatchLockKey(CustomAssetAttribute $attribute, $asset, string $channel, string $recipient): string
    {
        return implode(':', [
            'asset-reminder',
            CarbonImmutable::now()->toDateString(),
            $attribute->getKey() ?: 'attribute-none',
            $asset?->id ?: 'asset-none',
            $channel,
            sha1($recipient),
        ]);
    }

    protected function acquireDailyDispatchLock(string $key): bool
    {
        return Cache::add($key, true, now()->addHours(30));
    }

    protected function sendEmailNotification(string $subject, string $message, $asset, string $email): bool
    {
        try {
            Mail::raw($message, function ($mail) use ($email, $subject) {
                $mail->to($email)->subject($subject);
            });

            return true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim email pengingat aset: '.$e->getMessage(), [
                'asset_id' => $asset?->id,
                'email' => $email,
            ]);

            return false;
        }
    }
}
