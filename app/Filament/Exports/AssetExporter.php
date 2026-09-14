<?php

namespace App\Filament\Exports;

use App\Models\Asset;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class AssetExporter extends Exporter
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('purchase_date')->label('Tanggal Pembelian'),
            ExportColumn::make('businessEntity.name')->label('Badan Usaha'),
            ExportColumn::make('name')->label('Nama Aset'),
            ExportColumn::make('category.name')->label('Kategori'),
            ExportColumn::make('brand.name')->label('Merek'),
            ExportColumn::make('type')->label('Tipe'),
            ExportColumn::make('serial_number')->label('Serial Number'),
            ExportColumn::make('imei1')->label('IMEI 1'),
            ExportColumn::make('imei2')->label('IMEI 2'),
            ExportColumn::make('item_price')->label('Harga Aset'),
            ExportColumn::make('assetLocation.name')->label('Lokasi Aset'),
            ExportColumn::make('condition_status_label')->label('Status Aset'),
            ExportColumn::make('sold_at')->label('Tanggal Jual'),
            ExportColumn::make('sold_to')->label('Dijual Ke'),
            ExportColumn::make('sold_price')->label('Harga Jual'),
            ExportColumn::make('sale_document_path')->label('Dokumen Penjualan'),
            ExportColumn::make('sale_notes')->label('Catatan Penjualan'),
            ExportColumn::make('nbh_status_label')->label('Status NBH'),
            ExportColumn::make('nbhResponsible.name')->label('Penanggung Jawab NBH'),
            ExportColumn::make('nbh_reported_at')->label('Tanggal Insiden'),
            ExportColumn::make('recipient.name')->label('Penerima Aset'),
            ExportColumn::make('recipientBusinessEntity.name')->label('Badan Usaha Penerima'),
            ExportColumn::make('custom_attributes')
                ->label('Custom Attributes')
                ->state(function (Asset $record): string {
                    return $record->attributes
                        ->map(fn ($attr) => $attr->customAttribute?->name.': '.$attr->displayValue())
                        ->implode(', ');
                }),
            ExportColumn::make('qty')->label('Qty'),
        ];
    }

    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        $this->record = $record;

        $columns = $this->getCachedColumns();
        $data = [];

        foreach (array_keys($this->columnMap) as $column) {
            $data[] = array_key_exists($column, $columns)
                ? $columns[$column]->getFormattedState()
                : '';
        }

        return $data;
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with([
            'attributes.customAttribute',
            'businessEntity',
            'category',
            'brand',
            'assetLocation',
            'recipient',
            'recipientBusinessEntity',
            'nbhResponsible',
        ]);
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getJobQueue(): ?string
    {
        return 'default';
    }

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [];
    }

    public function getFileName(Export $export): string
    {
        return 'export_asset_'.now()->format('Y-m-d');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export aset selesai: '.Number::format($export->successful_rows).' baris.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }
}
