<?php

namespace Tests\Unit;

use App\Enums\AssetCondition;
use PHPUnit\Framework\TestCase;

class AssetConditionTest extends TestCase
{
    public function test_sold_status_is_exposed_in_asset_condition_options(): void
    {
        $this->assertSame('Dijual', AssetCondition::options()[AssetCondition::Sold->value]);
    }

    public function test_transferable_and_incident_status_groups_are_stable(): void
    {
        $this->assertSame(
            [AssetCondition::Available->value, AssetCondition::Transferred->value],
            AssetCondition::transferableValues(),
        );

        $this->assertSame(
            [AssetCondition::Lost->value, AssetCondition::Damaged->value],
            AssetCondition::incidentValues(),
        );
    }
}
