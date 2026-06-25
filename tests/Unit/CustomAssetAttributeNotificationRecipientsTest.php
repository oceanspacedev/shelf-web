<?php

namespace Tests\Unit;

use App\Models\CustomAssetAttribute;
use PHPUnit\Framework\TestCase;

class CustomAssetAttributeNotificationRecipientsTest extends TestCase
{
    public function test_existing_notifiable_attributes_default_to_whatsapp_channel(): void
    {
        $attribute = new CustomAssetAttribute([
            'is_notifiable' => true,
        ]);

        $this->assertSame(['whatsapp'], $attribute->notificationChannels());
    }

    public function test_notification_channels_and_recipients_are_normalized(): void
    {
        $attribute = new CustomAssetAttribute;
        $attribute->forceFill([
            'notification_channels' => ['email', 'whatsapp', 'email', 'sms'],
            'notification_recipient_user_ids' => ['3', 0, 3, 'invalid', 9],
            'notification_recipient_emails' => ['admin@example.com', 'bad-value', 'admin@example.com', 'ga@example.com'],
            'notification_recipient_whatsapp_numbers' => [' 08123456789 ', '+628123456789', '', '08123456789'],
        ]);

        $this->assertSame(['email', 'whatsapp'], $attribute->notificationChannels());
        $this->assertSame([3, 9], $attribute->notificationRecipientUserIds());
        $this->assertSame(['admin@example.com', 'ga@example.com'], $attribute->notificationRecipientEmails());
        $this->assertSame(['08123456789', '+628123456789'], $attribute->notificationRecipientWhatsappNumbers());
    }
}
