<?php

namespace Tests\Feature;

use App\Models\AssetQrLabelHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\AssetQrLabelTestCase;

class AssetQrLabelHistoryMigrationTest extends AssetQrLabelTestCase
{
    public function test_rollback_preserves_valid_references_and_accepts_deleted_assets(): void
    {
        [$deleted, $remaining] = $this->createAssets(2)->all();
        $histories = collect([
            [$deleted->id, $remaining->id],
            [$remaining->id],
            [],
        ])->map(fn (array $ids): AssetQrLabelHistory => AssetQrLabelHistory::create([
            'action' => AssetQrLabelHistory::ACTION_PRINT,
            'asset_ids' => $ids,
            'asset_count' => count($ids),
        ]));
        $deleted->delete();

        (require database_path('migrations/2026_09_24_000003_add_file_to_asset_qr_label_histories.php'))->down();
        (require database_path('migrations/2026_09_24_000002_batch_asset_qr_label_histories.php'))->down();

        $this->assertFalse(Schema::hasColumn('asset_qr_label_histories', 'asset_ids'));
        $this->assertSame(
            [null, $remaining->id, null],
            DB::table('asset_qr_label_histories')->orderBy('id')->pluck('asset_id')->all(),
        );
        $this->assertSame($histories->pluck('id')->all(), DB::table('asset_qr_label_histories')->orderBy('id')->pluck('id')->all());

        (require database_path('migrations/2026_09_24_000001_create_asset_qr_label_histories_table.php'))->down();
        $this->assertFalse(Schema::hasTable('asset_qr_label_histories'));
    }
}
