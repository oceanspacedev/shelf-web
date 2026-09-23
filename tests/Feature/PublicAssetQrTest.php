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
        $response->assertSee('Terakhir dilihat (GPS)');
        $response->assertSee('Belum ada data GPS');
        $response->assertDontSee('999999');
        $response->assertDontSee('Rp');
        $this->assertDatabaseHas('asset_qr_scans', ['asset_qr_id' => $qr->id]);
    }

    public function test_show_displays_previous_gps_last_seen(): void
    {
        $asset = Asset::factory()->create();
        $qr = $asset->fresh()->qr;

        AssetQrScan::query()->create([
            'asset_qr_id' => $qr->id,
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'user_agent' => 'seed',
        ]);

        $response = $this->get(route('qr.show', $qr));

        $response->assertOk();
        $response->assertSee('Terakhir dilihat (GPS)');
        $response->assertSee('-6.200000');
        $response->assertSee('106.816666');
        $response->assertSee('Buka di Google Maps');
        $response->assertSee('https://www.google.com/maps?q=-6.2,106.816666', false);
        $response->assertDontSee('Belum ada data GPS');
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

        $response = $this->postJson(route('qr.location', $qr), [
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);
        $response->assertJsonPath('maps_url', 'https://www.google.com/maps?q=-6.1754,106.8272');
        $this->assertNotEmpty($response->json('scanned_at'));

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
