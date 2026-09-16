<?php

namespace App\Services;

class WhatsappService
{
    /**
     * Send a WhatsApp message via WagHub (`WAG_URL` + `WAG_TOKEN`).
     */
    public static function send(string $phoneNumber, string $message): bool
    {
        return app(WhatsAppGateway::class)->send($phoneNumber, $message);
    }

    /**
     * Normalize a phone number to gateway format (international, no plus sign).
     */
    public static function normalizeNumber(?string $phoneNumber): ?string
    {
        return app(WhatsAppGateway::class)->normalizeTarget($phoneNumber);
    }
}
