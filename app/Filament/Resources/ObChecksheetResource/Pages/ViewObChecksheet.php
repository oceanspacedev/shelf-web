<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewObChecksheet extends ViewRecord
{
    protected static string $resource = ObChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
