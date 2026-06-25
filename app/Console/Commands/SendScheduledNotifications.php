<?php

namespace App\Console\Commands;

use App\Models\CustomAssetAttribute;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
            ->map(fn ($recipient) => $this->normalizeWhatsappTarget($recipient))
            ->filter()
            ->values()
            ->all();

        if ($recipients === [] && filled(config('services.fonnte.default_target'))) {
            $recipients[] = $this->normalizeWhatsappTarget(config('services.fonnte.default_target'));
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

    protected function normalizeWhatsappTarget(string|int|null $phoneNumber): ?string
    {
        if (! is_string($phoneNumber) && ! is_numeric($phoneNumber)) {
            return null;
        }

        $target = preg_replace('/\D+/', '', (string) $phoneNumber);

        if (! filled($target)) {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('services.fonnte.country_code', '62'));

        if (filled($countryCode) && str_starts_with($target, '0')) {
            return $countryCode.substr($target, 1);
        }

        return $target;
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

    protected function sendWhatsappNotification(string $message, $asset, string $phoneNumber): bool
    {
        $apiEndpoint = config('services.fonnte.endpoint', 'https://api.fonnte.com/send');
        $token = config('services.fonnte.token');

        if (! filled($apiEndpoint) || ! filled($token)) {
            Log::warning('Konfigurasi WhatsApp notifikasi aset belum lengkap.', [
                'asset_id' => $asset?->id,
                'receiver' => $phoneNumber,
                'provider' => 'fonnte',
            ]);

            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.fonnte.timeout', 10))
                ->retry(
                    (int) config('services.fonnte.retry_times', 2),
                    (int) config('services.fonnte.retry_sleep', 500),
                    throw: false,
                )
                ->withHeaders([
                    'Authorization' => $token,
                ])
                ->post($apiEndpoint, [
                    'target' => $phoneNumber,
                    'message' => $message,
                    'countryCode' => config('services.fonnte.country_code', '62'),
                ]);

            if ($response->failed() || $response->json('status') === false) {
                Log::error('Gagal mengirim pesan WhatsApp via Fonnte.', [
                    'asset_id' => $asset?->id,
                    'receiver' => $phoneNumber,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('Gagal mengirim pesan WhatsApp via Fonnte: '.$e->getMessage(), [
                'asset_id' => $asset?->id,
                'receiver' => $phoneNumber,
            ]);

            return false;
        }
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
