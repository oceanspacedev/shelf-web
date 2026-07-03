<?php

namespace Tests\Feature;

use App\Console\Commands\SendScheduledNotifications;
use App\Models\Asset;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use App\Services\WhatsAppGateway;
use App\Services\WhatsappService;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

class FonnteWhatsappNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp_gateway.min_seconds_between_sends', 0);
        config()->set('services.whatsapp_gateway.fallback_enabled', false);
    }

    public function test_whatsapp_reminder_is_sent_using_waha_payload(): void
    {
        config([
            'services.whatsapp_gateway.provider' => 'waha',
            'services.whatsapp_gateway.waha.base_url' => 'http://waha.local',
            'services.whatsapp_gateway.waha.api_key' => 'waha-secret',
            'services.whatsapp_gateway.waha.session' => 'default',
            'services.whatsapp_gateway.country_code' => '62',
        ]);

        $this->app->instance(WhatsAppGateway::class, new WhatsAppGateway(
            new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(201, [], '{"id":"msg-1"}'),
            ]))])
        ));

        $command = app(SendScheduledNotifications::class);
        $method = new ReflectionMethod($command, 'sendWhatsappNotification');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($command, 'Pesan pengingat STNK', null, '081234567890'));
    }

    public function test_whatsapp_reminder_falls_back_to_fonnte_when_waha_fails(): void
    {
        config([
            'services.whatsapp_gateway.provider' => 'waha',
            'services.whatsapp_gateway.fallback_provider' => 'fonnte',
            'services.whatsapp_gateway.fallback_enabled' => true,
            'services.whatsapp_gateway.waha.base_url' => 'http://waha.local',
            'services.whatsapp_gateway.fonnte.endpoint' => 'https://api.fonnte.com/send',
            'services.whatsapp_gateway.fonnte.token' => 'secret-token',
            'services.whatsapp_gateway.country_code' => '62',
        ]);

        $this->app->instance(WhatsAppGateway::class, new WhatsAppGateway(
            new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(500, [], '{"error":"session down"}'),
                new Response(200, [], '{"status":true}'),
            ]))])
        ));

        $command = app(SendScheduledNotifications::class);
        $method = new ReflectionMethod($command, 'sendWhatsappNotification');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($command, 'Pesan pengingat STNK', null, '081234567890'));
    }

    public function test_whatsapp_reminder_is_sent_using_fonnte_when_waha_is_not_configured(): void
    {
        config([
            'services.whatsapp_gateway.provider' => 'fonnte',
            'services.whatsapp_gateway.fonnte.endpoint' => 'https://api.fonnte.com/send',
            'services.whatsapp_gateway.fonnte.token' => 'secret-token',
            'services.whatsapp_gateway.country_code' => '62',
        ]);

        $this->app->instance(WhatsAppGateway::class, new WhatsAppGateway(
            new Client(['handler' => HandlerStack::create(new MockHandler([
                new Response(200, [], '{"status":true}'),
            ]))])
        ));

        $this->assertTrue(WhatsappService::send('081234567890', 'Pesan pengingat STNK'));
    }

    public function test_whatsapp_recipients_are_normalized(): void
    {
        config([
            'services.whatsapp_gateway.country_code' => '62',
        ]);

        $attribute = new CustomAssetAttribute;
        $attribute->forceFill([
            'notification_recipient_whatsapp_numbers' => [
                '+62 812-3456-7890',
                '0812 3456 7890',
                '6281234567890',
            ],
        ]);

        $command = app(SendScheduledNotifications::class);
        $method = new ReflectionMethod($command, 'resolveWhatsappRecipients');
        $method->setAccessible(true);

        $this->assertSame(['6281234567890'], $method->invoke($command, $attribute));
    }

    public function test_whatsapp_recipients_are_resolved_from_selected_internal_users(): void
    {
        config([
            'services.whatsapp_gateway.country_code' => '62',
            'services.whatsapp_gateway.default_target' => null,
        ]);

        $attribute = new CustomAssetAttribute;
        $attribute->forceFill([
            'notification_recipient_user_ids' => [5, 6],
        ]);

        $command = new class extends SendScheduledNotifications
        {
            protected function recipientUsers(CustomAssetAttribute $attribute): Collection
            {
                return collect([
                    new User([
                        'name' => 'Bayu',
                        'email' => 'bayu@example.com',
                        'whatsapp_number' => '0812 3456 7890',
                    ]),
                    new User([
                        'name' => 'User Tanpa WA',
                        'email' => 'tanpa-wa@example.com',
                    ]),
                ]);
            }
        };

        $method = new ReflectionMethod($command, 'resolveWhatsappRecipients');
        $method->setAccessible(true);

        $this->assertSame(['6281234567890'], $method->invoke($command, $attribute));
    }

    public function test_same_whatsapp_reminder_is_not_sent_more_than_once_per_day(): void
    {
        CarbonImmutable::setTestNow('2026-07-23 09:00:00');
        Cache::flush();

        config([
            'services.whatsapp_gateway.provider' => 'waha',
            'services.whatsapp_gateway.waha.base_url' => 'http://waha.local',
            'services.whatsapp_gateway.waha.session' => 'default',
            'services.whatsapp_gateway.country_code' => '62',
        ]);

        $gateway = new class extends WhatsAppGateway
        {
            public int $sendCount = 0;

            public function send($phoneNumber, string $message): bool
            {
                $this->sendCount++;

                return true;
            }
        };

        $this->app->instance(WhatsAppGateway::class, $gateway);

        $attribute = new CustomAssetAttribute([
            'notification_channels' => [CustomAssetAttribute::CHANNEL_WHATSAPP],
            'notification_recipient_whatsapp_numbers' => ['081234567890'],
        ]);
        $attribute->id = 77;

        $asset = new Asset(['name' => 'Mobil Operasional']);
        $asset->id = 55;

        $command = app(SendScheduledNotifications::class);
        $method = new ReflectionMethod($command, 'sendNotification');
        $method->setAccessible(true);

        $method->invoke($command, 'Pesan pengingat STNK', $asset, $attribute, 'Pengingat STNK');
        $method->invoke($command, 'Pesan pengingat STNK', $asset, $attribute, 'Pengingat STNK');

        $this->assertSame(1, $gateway->sendCount);

        CarbonImmutable::setTestNow();
    }
}
