<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetQrScan;
use App\Services\AssetQrScanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetQrScanServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_record_creates_scan_without_coords(): void
    {
        $asset = Asset::factory()->create();
        $qr = $asset->fresh()->qr;
        $service = app(AssetQrScanService::class);

        $scan = $service->record($qr, null, 'PHPUnit');

        $this->assertInstanceOf(AssetQrScan::class, $scan);
        $this->assertSame($qr->id, $scan->asset_qr_id);
        $this->assertNull($scan->latitude);
        $this->assertSame('PHPUnit', $scan->user_agent);
    }

    public function test_attach_location_updates_scan(): void
    {
        $asset = Asset::factory()->create();
        $qr = $asset->fresh()->qr;
        $service = app(AssetQrScanService::class);
        $scan = $service->record($qr, null, 'PHPUnit');

        $updated = $service->attachLocation($scan, -6.2, 106.8);

        $this->assertEqualsWithDelta(-6.2, $updated->latitude, 0.0001);
        $this->assertEqualsWithDelta(106.8, $updated->longitude, 0.0001);
    }
}
