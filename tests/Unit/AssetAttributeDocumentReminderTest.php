<?php

namespace Tests\Unit;

use App\Models\AssetAttribute;
use App\Models\CustomAssetAttribute;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AssetAttributeDocumentReminderTest extends TestCase
{
    public function test_document_attribute_is_reminded_daily_from_offset_until_renewed(): void
    {
        $attribute = $this->makeDocumentAttribute(notificationOffset: 7);
        $assetAttribute = $this->makeAssetAttribute($attribute, [
            'expires_at' => '2026-07-30',
            'document_number' => 'STNK-001',
            'document_path' => 'asset-documents/stnk-2026.pdf',
        ]);

        $this->assertFalse($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-22')));
        $this->assertTrue($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-23')));
        $this->assertTrue($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-30')));
        $this->assertTrue($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-31')));
        $this->assertSame('perlu_diperbarui', $assetAttribute->expiryReminderStatusOn(CarbonImmutable::parse('2026-07-23')));
        $this->assertSame('jatuh_tempo_hari_ini', $assetAttribute->expiryReminderStatusOn(CarbonImmutable::parse('2026-07-30')));
        $this->assertSame('expired', $assetAttribute->expiryReminderStatusOn(CarbonImmutable::parse('2026-07-31')));
    }

    public function test_renewing_document_to_new_expiry_date_stops_current_reminder_window(): void
    {
        $attribute = $this->makeDocumentAttribute(notificationOffset: 7);
        $assetAttribute = $this->makeAssetAttribute($attribute, [
            'expires_at' => '2027-07-30',
            'document_number' => 'STNK-002',
            'document_path' => 'asset-documents/stnk-2027.pdf',
        ]);

        $this->assertFalse($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-31')));
        $this->assertSame('aman', $assetAttribute->expiryReminderStatusOn(CarbonImmutable::parse('2026-07-31')));
    }

    public function test_legacy_date_value_still_works_for_existing_date_attributes(): void
    {
        $attribute = new CustomAssetAttribute([
            'type' => 'date',
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => 14,
        ]);

        $assetAttribute = new AssetAttribute([
            'attribute_value' => '2026-07-30',
        ]);
        $assetAttribute->setRelation('customAttribute', $attribute);

        $this->assertFalse($assetAttribute->isDocumentExpiryAttribute());
        $this->assertTrue($assetAttribute->shouldSendExpiryReminderOn(CarbonImmutable::parse('2026-07-16')));
        $this->assertSame('2026-07-30', $assetAttribute->expiryDate()?->toDateString());
    }

    private function makeDocumentAttribute(int $notificationOffset): CustomAssetAttribute
    {
        return new CustomAssetAttribute([
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => $notificationOffset,
        ]);
    }

    private function makeAssetAttribute(CustomAssetAttribute $attribute, array $value): AssetAttribute
    {
        $assetAttribute = new AssetAttribute([
            'attribute_value' => AssetAttribute::documentValue($value),
        ]);
        $assetAttribute->setRelation('customAttribute', $attribute);

        return $assetAttribute;
    }
}
