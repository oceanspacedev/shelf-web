<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditObChecksheet extends EditRecord
{
    protected static string $resource = ObChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
