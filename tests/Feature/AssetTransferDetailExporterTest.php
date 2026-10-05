<?php

namespace Tests\Feature;

use App\Filament\Exports\AssetTransferDetailExporter;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Tests\TestCase;

class AssetTransferDetailExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_defines_queued_filament_export_columns(): void
    {
        $names = array_map(
            fn ($column) => $column->getName(),
            AssetTransferDetailExporter::getColumns(),
        );

        $this->assertSame([
            'assetTransfer.letter_number',
            'assetTransfer.businessEntity.name',
            'status',
            'assetTransfer.fromUser.name',
            'assetTransfer.toUser.name',
            'assetTransfer.transfer_date',
            'asset.name',
            'asset.serial_number',
            'equipment',
            'document',
        ], $names);
    }

    public function test_completed_notification_mentions_successful_row_count(): void
    {
        $export = new Export([
            'successful_rows' => 12,
            'total_rows' => 12,
        ]);

        $this->assertStringContainsString('12', AssetTransferDetailExporter::getCompletedNotificationBody($export));
    }

    public function test_asset_transfer_list_uses_filament_export_action(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/AssetTransferResource/Pages/ListAssetTransfers.php'));

        $this->assertStringContainsString('Filament\\Actions\\ExportAction', $source);
        $this->assertStringContainsString('AssetTransferDetailExporter::class', $source);
    }

    public function test_uses_exports_queue_so_horizon_can_isolate_spreadsheet_work(): void
    {
        $exporter = new AssetTransferDetailExporter(new Export, [], []);

        $this->assertSame('exports', $exporter->getJobQueue());
        $this->assertSame([], $exporter->getJobMiddleware());
    }

    public function test_modify_query_converts_transfers_to_detail_rows_and_eager_loads(): void
    {
        $query = AssetTransferDetailExporter::modifyQuery(
            AssetTransfer::query()->whereRaw('0 = 1'),
        );

        $this->assertInstanceOf(AssetTransferDetail::class, $query->getModel());

        $eagerLoads = array_keys($query->getEagerLoads());
        $this->assertContains('assetTransfer.businessEntity', $eagerLoads);
        $this->assertContains('assetTransfer.fromUser', $eagerLoads);
        $this->assertContains('assetTransfer.toUser', $eagerLoads);
        $this->assertContains('asset', $eagerLoads);
    }

    public function test_modify_query_keeps_detail_model_when_already_details(): void
    {
        $query = AssetTransferDetailExporter::modifyQuery(AssetTransferDetail::query());

        $this->assertInstanceOf(AssetTransferDetail::class, $query->getModel());
        $this->assertContains('asset', array_keys($query->getEagerLoads()));
    }

    public function test_export_row_keeps_column_alignment_when_map_has_unknown_keys(): void
    {
        $detail = new AssetTransferDetail(['equipment' => 'Charger']);
        $detail->setRelation('assetTransfer', new AssetTransfer([
            'letter_number' => 'BA/000001',
            'document' => null,
        ]));
        $detail->setRelation('asset', new Asset([
            'name' => 'Laptop',
            'serial_number' => 'SN-1',
        ]));
        $detail->assetTransfer->setRelation('businessEntity', null);
        $detail->assetTransfer->setRelation('fromUser', null);
        $detail->assetTransfer->setRelation('toUser', null);

        $row = (new AssetTransferDetailExporter(new Export, [
            'equipment' => 'Keterangan Peralatan',
            'asset.name' => 'Nama Aset',
            'missing_column' => 'Missing',
        ], []))($detail);

        $this->assertSame(['Charger', 'Laptop', ''], $row);
    }
}
