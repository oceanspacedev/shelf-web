<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AssetNotificationService;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class UserEmailSanitizationTest extends TestCase
{
    public function test_user_email_rejects_line_breaks(): void
    {
        $user = new User;

        $this->expectException(InvalidArgumentException::class);

        $user->email = "requester@example.com\r\nBcc: attacker@example.com";
    }

    public function test_notification_service_skips_unsafe_legacy_email(): void
    {
        Mail::fake();

        $user = new User;
        $user->setRawAttributes([
            'id' => 1,
            'name' => 'Requester',
            'email' => "requester@example.com\r\nBcc: attacker@example.com",
            'whatsapp_number' => null,
        ], true);

        $result = AssetNotificationService::send($user, 'Subject', 'Message');

        $this->assertFalse($result['email']);
        Mail::assertNothingSent();
    }
}
