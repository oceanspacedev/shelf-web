<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Models\User;
use App\Services\AssetQrLabelHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetQrLabelHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_print_label_records_history_with_user_and_timestamp(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $asset = Asset::factory()->create(['name' => 'Single Print Asset']);

        $this->actingAs($user)
            ->get(route('assets.qr-label.print', $asset))
            ->assertOk();

        $history = AssetQrLabelHistory::query()
            ->where('user_id', $user->id)
            ->where('action', AssetQrLabelHistory::ACTION_PRINT)
            ->latest('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame([$asset->id], $history->asset_ids);
        $this->assertSame(1, $history->asset_count);
        $this->assertSame('Single Print Asset', $history->asset_summary);
        $this->assertNotEmpty($history->file_path);
        $this->assertTrue(Storage::disk('local')->exists($history->file_path));
        $this->assertStringEndsWith('.pdf', (string) $history->file_name);
    }

    public function test_bulk_print_records_one_history_for_all_assets(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $assets = Asset::factory()->count(3)->create();
        $ids = $assets->pluck('id')->implode(',');

        $this->actingAs($user)
            ->get(route('assets.qr-labels.print', ['ids' => $ids]))
            ->assertOk();

        $histories = AssetQrLabelHistory::query()
            ->where('user_id', $user->id)
            ->where('action', AssetQrLabelHistory::ACTION_PRINT)
            ->get();

        $this->assertCount(1, $histories);

        $history = $histories->first();
        $this->assertSame(3, $history->asset_count);
        $this->assertSame($assets->pluck('id')->map(fn ($id) => (int) $id)->all(), $history->asset_ids);
        $this->assertTrue(Storage::disk('local')->exists($history->file_path));

        foreach ($assets as $asset) {
            $this->assertStringContainsString($asset->name, (string) $history->asset_summary);
        }
    }

    public function test_history_service_records_one_download_batch_with_pdf_file(): void
    {
        $user = User::factory()->create();
        $assets = Asset::factory()->count(2)->create();

        $history = app(AssetQrLabelHistoryService::class)->record(
            $user,
            AssetQrLabelHistory::ACTION_DOWNLOAD_PDF,
            $assets,
        );

        $this->assertNotNull($history);
        $this->assertSame(2, $history->asset_count);
        $this->assertSame($assets->pluck('id')->map(fn ($id) => (int) $id)->all(), $history->asset_ids);
        $this->assertTrue($history->hasStoredFile());

        $response = app(AssetQrLabelHistoryService::class)->download($history);
        $this->assertSame(200, $response->getStatusCode());
    }
}
