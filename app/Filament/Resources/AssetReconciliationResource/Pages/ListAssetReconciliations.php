<?php

namespace App\Filament\Resources\AssetReconciliationResource\Pages;

use App\Filament\Actions\ExportCsaAuditFormatAction;
use App\Filament\Resources\AssetReconciliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssetReconciliations extends ListRecords
{
    protected static string $resource = AssetReconciliationResource::class;

    public function getSubheading(): ?string
    {
        return 'Alur sync CSA: 0 Export Format → isi Fisik/Selisih → 1 Import → 2 Laporan → 3 Apply. Data Shelf baru berubah setelah Apply.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportCsaAuditFormatAction::make(),
            CreateAction::make()
                ->label('1. Import Audit CSA')
                ->icon('heroicon-o-document-magnifying-glass'),
        ];
    }
}
