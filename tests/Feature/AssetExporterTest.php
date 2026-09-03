<?php

namespace Tests\Feature;

use App\Filament\Exports\AssetExporter;
use App\Models\Asset;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Tests\TestCase;

class AssetExporterTest extends TestCase
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
            AssetExporter::getColumns(),
        );

        $this->assertSame([
            'purchase_date',
            'businessEntity.name',
            'name',
            'category.name',
            'brand.name',
            'type',
            'serial_number',
            'imei1',
            'imei2',
            'item_price',
            'assetLocation.name',
            'condition_status_label',
            'sold_at',
            'sold_to',
            'sold_price',
            'sale_document_path',
            'sale_notes',
            'nbh_status_label',
            'nbhResponsible.name',
            'nbh_reported_at',
            'recipient.name',
            'recipientBusinessEntity.name',
            'custom_attributes',
        ], $names);
    }

    public function test_completed_notification_mentions_successful_row_count(): void
    {
        $export = new Export([
            'successful_rows' => 12,
            'total_rows' => 12,
        ]);

        $this->assertStringContainsString('12', AssetExporter::getCompletedNotificationBody($export));
    }

    public function test_asset_list_uses_filament_export_action(): void
    {
        $source = file_get_contents(app_path('Filament/Resources/AssetResource/Pages/ListAssets.php'));

        $this->assertStringContainsString('Filament\\Actions\\ExportAction', $source);
        $this->assertStringContainsString('AssetExporter::class', $source);
        $this->assertStringNotContainsString('ExportAssetListAction', $source);
    }

    public function test_uses_default_queue_so_legacy_export_jobs_cannot_block_it(): void
    {
        $exporter = new AssetExporter(new Export, [], []);

        $this->assertSame('default', $exporter->getJobQueue());
    }

    public function test_modify_query_eager_loads_export_relations(): void
    {
        $eagerLoads = array_keys(AssetExporter::modifyQuery(Asset::query())->getEagerLoads());

        $this->assertContains('businessEntity', $eagerLoads);
        $this->assertContains('attributes.customAttribute', $eagerLoads);
        $this->assertContains('nbhResponsible', $eagerLoads);
    }
}
