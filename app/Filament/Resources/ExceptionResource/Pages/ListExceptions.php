<?php

namespace App\Filament\Resources\ExceptionResource\Pages;

use App\Filament\Resources\ExceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListExceptions extends ListRecords
{
    protected static string $resource = ExceptionResource::class;

    protected function getTableEmptyStateIcon(): ?string
    {
        return static::$resource::getNavigationIcon();
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return __('filament-exceptions::filament-exceptions.empty_list');
    }
}
