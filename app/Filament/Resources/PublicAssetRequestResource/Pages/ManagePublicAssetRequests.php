<?php

namespace App\Filament\Resources\PublicAssetRequestResource\Pages;

use App\Filament\Resources\PublicAssetRequestResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Resources\Pages\ManageRecords;

class ManagePublicAssetRequests extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = PublicAssetRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
