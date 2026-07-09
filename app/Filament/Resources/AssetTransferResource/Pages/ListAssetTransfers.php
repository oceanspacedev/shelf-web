<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAssetTransfers extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = AssetTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
