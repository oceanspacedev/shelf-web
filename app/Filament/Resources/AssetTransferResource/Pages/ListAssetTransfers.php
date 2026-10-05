<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Exports\AssetTransferDetailExporter;
use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetTransfers extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = AssetTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export')
                ->color('warning')
                ->exporter(AssetTransferDetailExporter::class)
                ->chunkSize(250)
                ->columnMappingColumns(2)
                ->visible(fn () => auth()->user()?->can('export', AssetTransfer::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
