<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Exports\ObChecksheetExporter;
use App\Filament\Resources\ObChecksheetResource;
use App\Models\ObChecksheet;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListObChecksheets extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = ObChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(ObChecksheetExporter::class)
                ->label('Export')
                ->color('warning')
                ->visible(fn () => auth()->user()?->can('export', ObChecksheet::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
