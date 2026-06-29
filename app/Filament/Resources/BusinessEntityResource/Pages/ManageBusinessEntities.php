<?php

namespace App\Filament\Resources\BusinessEntityResource\Pages;

use App\Filament\Resources\BusinessEntityResource;
use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageBusinessEntities extends ManageRecords
{
    use HasResizableColumn;

    protected static string $resource = BusinessEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
