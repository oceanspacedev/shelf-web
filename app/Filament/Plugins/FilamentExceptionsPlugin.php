<?php

namespace App\Filament\Plugins;

use App\Filament\Resources\ExceptionResource;
use BezhanSalleh\FilamentExceptions\FilamentExceptions;
use BezhanSalleh\FilamentExceptions\FilamentExceptionsPlugin as BaseFilamentExceptionsPlugin;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use Filament\Panel;

class FilamentExceptionsPlugin extends BaseFilamentExceptionsPlugin
{
    public function register(Panel $panel): void
    {
        if (is_null(FilamentExceptions::getModel())) {
            FilamentExceptions::model(Exception::class);
        }

        $panel->resources([
            ExceptionResource::class,
        ]);
    }
}
