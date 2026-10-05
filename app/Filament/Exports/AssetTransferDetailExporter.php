<?php

namespace App\Filament\Exports;

use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Support\StoredFile;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class AssetTransferDetailExporter extends Exporter
{
    protected static ?string $model = AssetTransferDetail::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('assetTransfer.letter_number')
                ->label('Nomor BA'),

            ExportColumn::make('assetTransfer.businessEntity.name')
                ->label('Badan Usaha'),

            ExportColumn::make('status')
                ->label('Status')
                ->state(fn (AssetTransferDetail $record): string => $record->assetTransfer?->status ?? '-'),

            ExportColumn::make('assetTransfer.fromUser.name')
                ->label('Dari Pengguna'),

            ExportColumn::make('assetTransfer.toUser.name')
                ->label('Ke Pengguna'),

            ExportColumn::make('assetTransfer.transfer_date')
                ->label('Tanggal Transfer')
                ->state(function (AssetTransferDetail $record): ?string {
                    $date = $record->assetTransfer?->transfer_date;

                    if (! $date) {
                        return null;
                    }

                    return $date instanceof \DateTimeInterface
                        ? $date->format('Y-m-d')
                        : (string) $date;
                }),

            ExportColumn::make('asset.name')
                ->label('Nama Aset'),

            ExportColumn::make('asset.serial_number')
                ->label('Serial Number'),

            ExportColumn::make('equipment')
                ->label('Keterangan Peralatan'),

            ExportColumn::make('document')
                ->label('Dokumen')
                ->state(function (AssetTransferDetail $record): string {
                    $document = $record->assetTransfer?->document;

                    return $document ? StoredFile::downloadUrl(ltrim($document, '/')) : '-';
                }),
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
        if ($query->getModel() instanceof AssetTransfer) {
            $query = AssetTransferDetail::query()
                ->whereIn(
                    'asset_transfer_id',
                    (clone $query)->select($query->getModel()->getQualifiedKeyName()),
                );
        }

        return $query->with([
            'assetTransfer.businessEntity',
            'assetTransfer.fromUser',
            'assetTransfer.toUser',
            'asset',
        ]);
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getJobQueue(): ?string
    {
        return 'exports';
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
        return 'export_asset_transfer_'.now()->format('Y-m-d');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export transfer aset selesai: '.Number::format($export->successful_rows).' baris.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' baris gagal.';
        }

        return $body;
    }
}
