<?php

namespace App\Filament\Exports;

use App\Models\AssetService;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class AssetServiceExporter extends Exporter
{
    protected static ?string $model = AssetService::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('service_number')
                ->label('No. Servis'),

            ExportColumn::make('asset.name')
                ->label('Nama Aset'),

            ExportColumn::make('asset.serial_number')
                ->label('No. Seri / IMEI'),

            ExportColumn::make('provider_type')
                ->label('Tipe Pelaksana')
                ->state(fn (AssetService $record): string => $record->provider_type === 'internal' ? 'Internal' : 'Eksternal (Vendor)'),

            ExportColumn::make('technician')
                ->label('Pelaksana Servis')
                ->state(function (AssetService $record): string {
                    if ($record->provider_type === 'internal') {
                        return $record->servicedByUser?->name ?? 'Staf Internal';
                    }
                    return $record->vendor?->name ?? $record->technician_name ?? 'Vendor Luar';
                }),

            ExportColumn::make('contact_number')
                ->label('No. Kontak'),

            ExportColumn::make('service_date')
                ->label('Tanggal Servis')
                ->state(fn (AssetService $record): ?string => $record->service_date?->format('d/m/Y')),

            ExportColumn::make('completion_date')
                ->label('Tanggal Selesai')
                ->state(fn (AssetService $record): ?string => $record->completion_date?->format('d/m/Y') ?? '-'),

            ExportColumn::make('status')
                ->label('Status')
                ->state(fn (AssetService $record): string => $record->status?->label() ?? (string) $record->status),

            ExportColumn::make('issue_description')
                ->label('Keluhan / Kerusakan'),

            ExportColumn::make('action_taken')
                ->label('Tindakan Perbaikan'),

            ExportColumn::make('total_cost')
                ->label('Total Biaya (Rp)')
                ->state(fn (AssetService $record): string => 'Rp ' . number_format($record->total_cost ?: 0, 0, ',', '.')),

            ExportColumn::make('receipt_document_path')
                ->label('Dokumen / Nota')
                ->state(fn (AssetService $record): ?string => $record->receipt_document_path ? asset('storage/' . ltrim($record->receipt_document_path, '/')) : null),

            ExportColumn::make('notes')
                ->label('Catatan'),

            ExportColumn::make('creator.name')
                ->label('Dibuat Oleh'),

            ExportColumn::make('created_at')
                ->label('Tanggal Dibuat')
                ->state(fn (AssetService $record): ?string => $record->created_at?->format('d/m/Y H:i:s')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Ekspor data servis aset Anda telah selesai dan ' . Number::format($export->successful_rows) . ' baris berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' baris gagal diekspor.';
        }

        return $body;
    }

    public function getFormats(): array
    {
        return [
            ExportFormat::Xlsx,
            ExportFormat::Csv,
        ];
    }

    public function getFileName(Export $export): string
    {
        return 'export_servis_aset_' . now()->format('Y-m-d_His');
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $user = auth()->user();

        $query = $query->with(['asset', 'servicedByUser', 'vendor', 'creator']);

        if ($user && ! $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('serviced_by_user_id', $user->id);
            });
        }

        return $query;
    }
}
