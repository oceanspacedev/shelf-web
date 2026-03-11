<?php

namespace App\Filament\Resources\PublicAssetRequestResource\Pages;

use App\Filament\Resources\PublicAssetRequestResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;

class ViewPublicAssetRequest extends ViewRecord
{
    protected static string $resource = PublicAssetRequestResource::class;

    public function getTitle(): string | Htmlable
    {
        return 'Detail Pengajuan Asset';
    }

    public function getSubheading(): string | Htmlable | null
    {
        $record = $this->getRecord();

        return "ID {$record->id} / {$record->requester_name} / {$record->item_name}";
    }

    public function getMaxContentWidth(): MaxWidth | string | null
    {
        return MaxWidth::Full;
    }
}
