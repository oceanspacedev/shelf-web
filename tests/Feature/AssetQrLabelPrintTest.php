<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetQrLabelPrintTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authenticated_user_can_open_print_page_with_qr_image(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $asset = Asset::factory()->create(['name' => 'Printer Test Asset']);
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

        $assets = Asset::factory()->count(3)->create();
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
        $asset = Asset::factory()->create();

        $this->get(route('assets.qr-label.print', $asset))
            ->assertRedirect();
    }
}
