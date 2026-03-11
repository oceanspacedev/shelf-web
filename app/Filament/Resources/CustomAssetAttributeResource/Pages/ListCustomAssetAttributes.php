<?php

namespace App\Filament\Resources\CustomAssetAttributeResource\Pages;

use App\Filament\Resources\CustomAssetAttributeResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomAssetAttributes extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = CustomAssetAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
