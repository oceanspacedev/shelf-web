<?php

namespace Tests\Feature;

use App\Console\Commands\SendScheduledNotifications;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class FonnteWhatsappNotificationTest extends TestCase
{
    public function test_whatsapp_reminder_is_sent_using_fonnte_payload(): void
    {
        config([
            'services.fonnte.endpoint' => 'https://api.fonnte.com/send',
            'services.fonnte.token' => 'secret-token',
            'services.fonnte.country_code' => '62',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

        $command = app(SendScheduledNotifications::class);
        $method = new ReflectionMethod($command, 'sendWhatsappNotification');
        $method->setAccessible(true);

        $method->invoke($command, 'Pesan pengingat STNK', null, '08123456789');

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.fonnte.com/send'
                && $request->hasHeader('Authorization', 'secret-token')
                && ($data['target'] ?? null) === '08123456789'
                && ($data['message'] ?? null) === 'Pesan pengingat STNK'
                && ($data['countryCode'] ?? null) === '62';
        });
    }
}
