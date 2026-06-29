<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappService
{
    /**
     * Send a WhatsApp message via Fonnte API.
     */
    public static function send(string $phoneNumber, string $message): bool
    {
        $apiEndpoint = config('services.fonnte.endpoint', 'https://api.fonnte.com/send');
        $token = config('services.fonnte.token');

        if (! filled($apiEndpoint) || ! filled($token)) {
            Log::warning('Konfigurasi WhatsApp notifikasi belum lengkap.', [
                'receiver' => $phoneNumber,
            ]);

            return false;
        }

        $normalized = self::normalizeNumber($phoneNumber);
        if (! $normalized) {
            Log::warning('Nomor WhatsApp tidak valid.', [
                'receiver' => $phoneNumber,
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
                    'target' => $normalized,
                    'message' => $message,
                    'countryCode' => config('services.fonnte.country_code', '62'),
                ]);

            if ($response->failed() || $response->json('status') !== true) {
                Log::error('Gagal mengirim pesan WhatsApp via Fonnte.', [
                    'receiver' => $normalized,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 1000),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan WhatsApp via Fonnte: '.$e->getMessage(), [
                'receiver' => $normalized,
            ]);

            return false;
        }
    }

    /**
     * Normalize a phone number to Fonnte format (starts with country code).
     */
    public static function normalizeNumber(?string $phoneNumber): ?string
    {
        if (blank($phoneNumber)) {
            return null;
        }

        $target = preg_replace('/\D+/', '', (string) $phoneNumber);

        if (! filled($target)) {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('services.fonnte.country_code', '62'));

        // Format internasional lama (0062...) -> strip prefix 00 sebelum logika country code,
        // agar 0062812... menjadi 62812..., bukan 62062812...
        if (filled($countryCode) && str_starts_with($target, '00')) {
            return ltrim($target, '0');
        }

        if (filled($countryCode) && str_starts_with($target, '0')) {
            return $countryCode.substr($target, 1);
        }

        return $target;
    }
}
