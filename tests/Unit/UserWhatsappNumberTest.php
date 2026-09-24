<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserWhatsappNumberTest extends TestCase
{
    public function test_user_whatsapp_number_can_be_mass_assigned(): void
    {
        $user = new User([
            'name' => 'BAYU MARSHARENO, S.KOM.I',
            'email' => 'bayu@example.com',
            'whatsapp_number' => '081234567890',
        ]);

        $this->assertSame('081234567890', $user->whatsapp_number);
    }

    public function test_login_number_is_normalized_without_changing_the_notification_contact(): void
    {
        $user = new User([
            'whatsapp_number' => '081234567890',
            'whatsapp_login_number' => '+62 812-3456-7890',
        ]);

        $this->assertSame('6281234567890', $user->whatsapp_login_number);
        $this->assertSame('081234567890', $user->whatsapp_number);
        $this->assertArrayNotHasKey('whatsapp_login_number', $user->toArray());

        $user->whatsapp_login_number = '';
        $this->assertNull($user->whatsapp_login_number);
    }

    public function test_invalid_login_number_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new User(['whatsapp_login_number' => '123456789012345@lid']);
    }
}
