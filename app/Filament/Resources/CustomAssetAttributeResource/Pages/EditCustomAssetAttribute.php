<?php

namespace App\Filament\Resources\CustomAssetAttributeResource\Pages;

use App\Filament\Resources\CustomAssetAttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomAssetAttribute extends EditRecord
{
    protected static string $resource = CustomAssetAttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
