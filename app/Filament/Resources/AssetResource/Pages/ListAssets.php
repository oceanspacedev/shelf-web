<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use App\Imports\AssetImport;
use Asmit\ResizedColumn\HasResizableColumn;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListAssets extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export')
                ->icon('heroicon-o-document-arrow-up')
                ->color('warning')
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withColumns([
                            Column::make('purchase_date')->heading('Tanggal Pembelian'),
                            Column::make('businessEntity.name')->heading('Badan Usaha'),
                            Column::make('name')->heading('Nama Aset'),
                            Column::make('category.name')->heading('Kategori'),
                            Column::make('brand.name')->heading('Merek'),
                            Column::make('type')->heading('Tipe'),
                            Column::make('serial_number')->heading('Serial Number'),
                            Column::make('imei1')->heading('IMEI 1'),
                            Column::make('imei2')->heading('IMEI 2'),
                            Column::make('item_price')->heading('Harga Aset'),
                            Column::make('assetLocation.name')->heading('Lokasi Aset'),
                            Column::make('condition_status_label')->heading('Status Aset'),
                            Column::make('nbh_status_label')->heading('Status NBH'),
                            Column::make('nbhResponsible.name')->heading('Penanggung Jawab NBH'),
                            Column::make('nbh_reported_at')->heading('Tanggal Insiden'),
                            Column::make('recipient.name')->heading('Penerima Aset'),
                            Column::make('recipientBusinessEntity.name')->heading('Badan Usaha Penerima'),
                        ])
                        ->withFilename('export_asset_'.date('Y-m-d')),
                ]),
            ExcelImportAction::make()
                ->color('success')
                ->use(AssetImport::class),
            Actions\CreateAction::make(),
        ];
    }
}
