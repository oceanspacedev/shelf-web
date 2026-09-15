<?php

namespace App\Filament\Resources\AssetServiceResource\Pages;

use App\Filament\Resources\AssetServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetService extends CreateRecord
{
    protected static string $resource = AssetServiceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
