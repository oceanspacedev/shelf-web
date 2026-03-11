<?php

namespace App\Filament\Resources\AssetLocationResource\Pages;

use App\Filament\Resources\AssetLocationResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAssetLocations extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = AssetLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
