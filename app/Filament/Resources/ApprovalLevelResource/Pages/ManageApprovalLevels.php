<?php

namespace App\Filament\Resources\ApprovalLevelResource\Pages;

use App\Filament\Resources\ApprovalLevelResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageApprovalLevels extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = ApprovalLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
