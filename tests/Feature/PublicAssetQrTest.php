<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetQrScan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicAssetQrTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_can_view_qr_page_with_location_and_qty(): void
    {
        $asset = Asset::factory()->create(['qty' => 3, 'item_price' => 999999]);
        $qr = $asset->fresh()->qr;

        $response = $this->get(route('qr.show', $qr));

        $response->assertOk();
        $response->assertSee($asset->name);
        $response->assertSee((string) $asset->qty);
        $response->assertDontSee('999999');
        $response->assertDontSee('Rp');
        $this->assertDatabaseHas('asset_qr_scans', ['asset_qr_id' => $qr->id]);
    }

    public function test_unknown_qr_returns_invalid_page(): void
    {
        $response = $this->get('/qr/01INVALIDULID000000000000');

        $response->assertNotFound();
    }

    public function test_location_post_updates_scan(): void
    {
        $asset = Asset::factory()->create();
        $qr = $asset->fresh()->qr;

        $this->get(route('qr.show', $qr))->assertOk();

        $this->postJson(route('qr.location', $qr), [
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ])->assertOk();

        $scan = AssetQrScan::where('asset_qr_id', $qr->id)->latest('id')->first();
        $this->assertNotNull($scan->latitude);
        $this->assertNotNull($scan->longitude);
    }

    public function test_regenerated_old_qr_is_not_found(): void
    {
        $asset = Asset::factory()->create();
        $oldId = $asset->fresh()->qr->id;
        app(\App\Services\AssetQrService::class)->regenerate($asset);

        $this->get('/qr/'.$oldId)->assertNotFound();
        $this->get(route('qr.show', $asset->fresh()->qr))->assertOk();
    }
}
