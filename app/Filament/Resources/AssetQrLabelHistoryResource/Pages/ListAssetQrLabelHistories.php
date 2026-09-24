<?php

namespace App\Filament\Resources\AssetQrLabelHistoryResource\Pages;

use App\Filament\Resources\AssetQrLabelHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListAssetQrLabelHistories extends ListRecords
{
    protected static string $resource = AssetQrLabelHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
