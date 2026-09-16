<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppGateway
{
    public const MESSAGES_PATH = '/api/v1/messages';

    public function __construct(
        private readonly ?Client $client = null,
    ) {}

    public function send($phoneNumber, string $message): bool
    {
        $target = $this->normalizeTarget($phoneNumber);

        if ($target === null) {
            Log::error('Gagal mengirim pesan WhatsApp: nomor tujuan kosong atau tidak valid.', [
                'receiver' => $phoneNumber,
            ]);

            return false;
        }

        $url = config('services.whatsapp_gateway.url');
        $token = config('services.whatsapp_gateway.token');

        if (! is_string($url) || trim($url) === '') {
            Log::error('WAG_URL tidak diatur di file .env');

            return false;
        }

        if (! is_string($token) || trim($token) === '') {
            Log::error('WAG_TOKEN tidak diatur di file .env');

            return false;
        }

        try {
            $response = $this->client()->post($this->messagesEndpoint($url), [
                'http_errors' => false,
                'timeout' => (float) config('services.whatsapp_gateway.timeout', 15),
                'connect_timeout' => (float) config('services.whatsapp_gateway.connect_timeout', 5),
                'headers' => [
                    'Authorization' => 'Bearer '.ltrim($token, 'Bearer '),
                    'Accept' => 'application/json',
                    'Idempotency-Key' => 'shelf-'.(string) str()->uuid(),
                ],
                'json' => [
                    'recipient' => [
                        'type' => 'phone',
                        'value' => $target,
                    ],
                    'message' => [
                        'type' => 'text',
                        'text' => $message,
                    ],
                    'purpose' => 'notification',
                    'mode' => 'sync',
                    'route_key' => 'default',
                    'client_reference' => 'shelf',
                ],
            ]);

            $body = (string) $response->getBody();

            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                Log::error('Gagal mengirim pesan WhatsApp via WAG.', [
                    'receiver' => $target,
                    'status' => $response->getStatusCode(),
                    'body' => mb_substr($body, 0, 1000),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim pesan WhatsApp via WAG: '.$e->getMessage(), [
                'receiver' => $target,
            ]);

            return false;
        }
    }

    public function normalizeTarget($phoneNumber): ?string
    {
        if ($phoneNumber === null) {
            return null;
        }

        $phoneNumber = trim((string) $phoneNumber);

        if ($phoneNumber === '') {
            return null;
        }

        if (str_ends_with($phoneNumber, '@g.us') || str_ends_with($phoneNumber, '@c.us')) {
            return $phoneNumber;
        }

        $target = preg_replace('/\D+/', '', $phoneNumber);
        $countryCode = preg_replace('/\D+/', '', (string) config('services.whatsapp_gateway.country_code', '62'));

        if ($target === null || $target === '') {
            return null;
        }

        if (str_starts_with($target, '00')) {
            $target = substr($target, 2);
        }

        if ($countryCode !== null && $countryCode !== '') {
            if (str_starts_with($target, '0')) {
                $target = $countryCode.ltrim($target, '0');
            } elseif (str_starts_with($target, '8')) {
                $target = $countryCode.$target;
            } elseif (str_starts_with($target, $countryCode.'0')) {
                $target = $countryCode.substr($target, strlen($countryCode) + 1);
            }
        }

        $digitCount = strlen($target);
        $minDigits = (int) config('services.whatsapp_gateway.min_digits', 10);
        $maxDigits = (int) config('services.whatsapp_gateway.max_digits', 15);

        if ($digitCount < $minDigits || $digitCount > $maxDigits) {
            return null;
        }

        return $target;
    }

    private function messagesEndpoint(string $baseUrl): string
    {
        $baseUrl = rtrim($baseUrl, '/');

        if (str_ends_with($baseUrl, self::MESSAGES_PATH)) {
            return $baseUrl;
        }

        return $baseUrl.self::MESSAGES_PATH;
    }

    private function client(): Client
    {
        return $this->client ?? new Client([
            'timeout' => (float) config('services.whatsapp_gateway.timeout', 15),
            'connect_timeout' => (float) config('services.whatsapp_gateway.connect_timeout', 5),
            'http_errors' => false,
        ]);
    }
}
