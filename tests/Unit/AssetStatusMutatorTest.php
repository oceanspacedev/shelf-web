<?php

namespace Tests\Unit;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use Tests\TestCase;

class AssetStatusMutatorTest extends TestCase
{
    public function test_setting_sold_status_marks_asset_unavailable_without_nbh(): void
    {
        $asset = new Asset();
        $asset->recipient_id = 99;
        $asset->recipient_business_entity_id = 88;

        $asset->condition_status = AssetCondition::Sold;

        $this->assertSame(AssetCondition::Sold->value, $asset->getAttributes()['condition_status']);
        $this->assertFalse($asset->getAttributes()['is_available']);
        $this->assertSame(NbhStatus::None->value, $asset->getAttributes()['nbh_status']);
        $this->assertNull($asset->getAttributes()['recipient_id']);
        $this->assertNull($asset->getAttributes()['recipient_business_entity_id']);
    }

    public function test_sold_assets_skip_recipient_validation_rules(): void
    {
        $asset = new Asset();
        $asset->condition_status = AssetCondition::Sold;

        $this->assertTrue($asset->checkValidRecipient());
    }

    public function test_leaving_sold_status_clears_sale_audit_attributes(): void
    {
        $asset = new Asset();
        $asset->condition_status = AssetCondition::Sold;
        $asset->sold_at = '2026-04-18';
        $asset->sold_to = 'PT Audit Jaya';
        $asset->sold_price = 0;
        $asset->sale_document_path = 'asset-sales/test.pdf';
        $asset->sale_notes = 'Nominal write-off';

        $asset->condition_status = AssetCondition::Available;

        $this->assertNull($asset->getAttributes()['sold_at']);
        $this->assertNull($asset->getAttributes()['sold_to']);
        $this->assertNull($asset->getAttributes()['sold_price']);
        $this->assertNull($asset->getAttributes()['sale_document_path']);
        $this->assertNull($asset->getAttributes()['sale_notes']);
    }
}
