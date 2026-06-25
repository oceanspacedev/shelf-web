<?php

namespace Tests\Feature;

use App\Console\Commands\SendScheduledNotifications;
use App\Models\Asset;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    public function test_whatsapp_recipients_are_normalized_for_fonnte(): void
    {
        config([
            'services.fonnte.country_code' => '62',
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
            'services.fonnte.country_code' => '62',
            'services.fonnte.default_target' => null,
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
            'services.fonnte.endpoint' => 'https://api.fonnte.com/send',
            'services.fonnte.token' => 'secret-token',
            'services.fonnte.country_code' => '62',
        ]);

        Http::fake([
            'https://api.fonnte.com/send' => Http::response(['status' => true], 200),
        ]);

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

        Http::assertSentCount(1);

        CarbonImmutable::setTestNow();
    }
}
