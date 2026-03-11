<?php

namespace App\Filament\Resources\JobTitleResource\Pages;

use App\Filament\Resources\JobTitleResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageJobTitles extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = JobTitleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
