<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Tests\Support\AssetQrLabelTestCase;

class AssetQrLabelPrintTest extends AssetQrLabelTestCase
{
    public function test_authenticated_user_can_open_print_page_with_qr_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $asset = Asset::create(['name' => 'Printer Test Asset']);
        $qr = $asset->fresh()->qr;

        $response = $this->actingAs($user)->get(route('assets.qr-label.print', $asset));

        $response->assertOk();
        $response->assertSee('Print Label QR');
        $response->assertSee('Printer Test Asset');
        $response->assertSee('data:image/png;base64,', false);
        $response->assertDontSee($qr->id);
    }

    public function test_authenticated_user_can_bulk_print_multiple_assets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $assets = $this->createAssets(3);
        $ids = $assets->pluck('id')->implode(',');

        $response = $this->actingAs($user)->get(route('assets.qr-labels.print', ['ids' => $ids]));

        $response->assertOk();
        $response->assertSee('3 label');
        foreach ($assets as $asset) {
            $response->assertSee($asset->name);
        }
        $response->assertSee('data:image/png;base64,', false);
    }

    public function test_guest_cannot_open_print_page(): void
    {
        $asset = $this->createAssets(1)->first();

        $this->get(route('assets.qr-label.print', $asset))
            ->assertRedirect();
    }

    public function test_user_without_asset_permission_cannot_print_or_create_history(): void
    {
        $user = User::factory()->create();
        $asset = $this->createAssets(1)->first();

        $this->actingAs($user)->get(route('assets.qr-label.print', $asset))->assertForbidden();
        $this->get(route('assets.qr-labels.print', ['ids' => $asset->id]))->assertForbidden();

        $this->assertDatabaseCount('asset_qr_label_histories', 0);
    }
}
