<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

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
}
