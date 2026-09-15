<?php

namespace App\Filament\Resources\AssetServiceResource\Pages;

use App\Filament\Exports\AssetServiceExporter;
use App\Filament\Resources\AssetServiceResource;
use App\Models\AssetService;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetServices extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = AssetServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(AssetServiceExporter::class)
                ->label('Export')
                ->color('warning')
                ->visible(fn () => auth()->user()?->can('export', AssetService::class) ?? true),
            CreateAction::make()
                ->label('Tambah Servis Aset'),
        ];
    }
}
