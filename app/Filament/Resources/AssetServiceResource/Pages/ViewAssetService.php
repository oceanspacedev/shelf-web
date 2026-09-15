<?php

namespace App\Filament\Resources\AssetServiceResource\Pages;

use App\Filament\Resources\AssetServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetService extends ViewRecord
{
    protected static string $resource = AssetServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
