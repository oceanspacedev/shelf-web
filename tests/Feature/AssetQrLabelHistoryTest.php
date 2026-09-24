<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Models\User;
use App\Services\AssetQrLabelHistoryService;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use Tests\Support\AssetQrLabelTestCase;

class AssetQrLabelHistoryTest extends AssetQrLabelTestCase
{
    public function test_print_label_records_history_with_user_and_timestamp(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $asset = Asset::create(['name' => 'Single Print Asset']);

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

        $assets = $this->createAssets(3);
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
        $assets = $this->createAssets(2);

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

    public function test_same_size_batches_in_the_same_second_keep_separate_pdf_files(): void
    {
        $this->freezeTime();
        $service = app(AssetQrLabelHistoryService::class);
        $first = $service->record(null, AssetQrLabelHistory::ACTION_PRINT, $this->createAssets(2, 'Batch A'));
        $originalPdf = Storage::disk('local')->get($first->file_path);

        $second = $service->record(null, AssetQrLabelHistory::ACTION_DOWNLOAD_PDF, $this->createAssets(2, 'Batch B'));

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertNotSame($first->file_name, $second->file_name);
        $this->assertSame($originalPdf, Storage::disk('local')->get($first->file_path));
        $this->assertNotSame($originalPdf, Storage::disk('local')->get($second->file_path));
        $this->assertDatabaseCount('asset_qr_label_histories', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_reprinting_the_same_asset_in_the_same_second_preserves_each_pdf(): void
    {
        $this->freezeTime();
        $assets = $this->createAssets(1);
        $service = app(AssetQrLabelHistoryService::class);
        $first = $service->record(null, AssetQrLabelHistory::ACTION_PRINT, $assets);
        $originalPdf = Storage::disk('local')->get($first->file_path);
        $assets->first()->update(['name' => 'Updated asset name']);

        $second = $service->record(null, AssetQrLabelHistory::ACTION_PRINT, $assets);

        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertSame($originalPdf, Storage::disk('local')->get($first->file_path));
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_failed_pdf_write_does_not_create_a_history(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);

        try {
            app(AssetQrLabelHistoryService::class)->record(null, AssetQrLabelHistory::ACTION_PRINT, $this->createAssets(1));
            $this->fail('A failed PDF write must stop history creation.');
        } catch (UnableToWriteFile $exception) {
            $this->assertStringContainsString('Gagal menyimpan PDF label QR.', $exception->getMessage());
            $this->assertDatabaseCount('asset_qr_label_histories', 0);
        }
    }

    public function test_failed_history_insert_removes_the_pdf(): void
    {
        $missingUser = new User;
        $missingUser->id = 999;

        try {
            app(AssetQrLabelHistoryService::class)->record($missingUser, AssetQrLabelHistory::ACTION_PRINT, $this->createAssets(1));
            $this->fail('An invalid user must fail the history foreign key constraint.');
        } catch (QueryException) {
            $this->assertDatabaseCount('asset_qr_label_histories', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }
}
