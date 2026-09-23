<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateObChecksheet extends CreateRecord
{
    protected static string $resource = ObChecksheetResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Pembersihan dimulai! Jangan lupa ambil Foto Sesudah jika sudah selesai.';
    }
}
