<?php

namespace App\Filament\Resources\ObTaskTemplateResource\Pages;

use App\Filament\Resources\ObTaskTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageObTaskTemplates extends ManageRecords
{
    protected static string $resource = ObTaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
