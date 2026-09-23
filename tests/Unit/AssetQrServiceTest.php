<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetQr;
use App\Services\AssetQrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetQrServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_for_asset_creates_one_qr(): void
    {
        $asset = Asset::factory()->create();
        $service = app(AssetQrService::class);

        $qr = $service->createForAsset($asset);

        $this->assertInstanceOf(AssetQr::class, $qr);
        $this->assertSame($asset->id, $qr->asset_id);
        $this->assertNotEmpty($qr->id);
        $this->assertSame(1, AssetQr::where('asset_id', $asset->id)->count());
    }

    public function test_ensure_for_asset_is_idempotent(): void
    {
        $asset = Asset::factory()->create();
        $service = app(AssetQrService::class);

        $first = $service->ensureForAsset($asset);
        $second = $service->ensureForAsset($asset);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AssetQr::where('asset_id', $asset->id)->count());
    }

    public function test_regenerate_replaces_qr_id(): void
    {
        $asset = Asset::factory()->create();
        $service = app(AssetQrService::class);
        $old = $service->createForAsset($asset);

        $new = $service->regenerate($asset);

        $this->assertNotSame($old->id, $new->id);
        $this->assertNull(AssetQr::find($old->id));
        $this->assertSame(1, AssetQr::where('asset_id', $asset->id)->count());
    }

    public function test_public_url_contains_qr_id(): void
    {
        $asset = Asset::factory()->create();
        $service = app(AssetQrService::class);
        $qr = $service->createForAsset($asset);

        $url = $service->publicUrl($qr);

        $this->assertStringEndsWith('/qr/'.$qr->id, $url);
    }

    public function test_png_returns_png_binary(): void
    {
        $asset = Asset::factory()->create();
        $service = app(AssetQrService::class);
        $qr = $service->createForAsset($asset);

        $png = $service->png($qr, 200);

        $this->assertNotEmpty($png);
        $this->assertSame("\x89PNG", substr($png, 0, 4));
    }
}
