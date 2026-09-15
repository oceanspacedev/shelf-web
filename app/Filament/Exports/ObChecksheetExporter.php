<?php

namespace App\Filament\Exports;

use App\Models\ObChecksheet;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class ObChecksheetExporter extends Exporter
{
    protected static ?string $model = ObChecksheet::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference_number')
                ->label('No. Referensi'),

            ExportColumn::make('user.name')
                ->label('Petugas OB'),

            ExportColumn::make('room')
                ->label('Nama Ruangan'),

            ExportColumn::make('status')
                ->label('Status')
                ->state(fn (ObChecksheet $record): string => match ($record->status) {
                    'in_progress' => 'Sedang Dikerjakan',
                    'completed' => 'Selesai',
                    default => $record->status ?? '-',
                }),

            ExportColumn::make('started_at')
                ->label('Waktu Mulai')
                ->state(fn (ObChecksheet $record): ?string => $record->started_at?->format('d/m/Y H:i:s')),

            ExportColumn::make('finished_at')
                ->label('Waktu Selesai')
                ->state(fn (ObChecksheet $record): ?string => $record->finished_at?->format('d/m/Y H:i:s')),

            ExportColumn::make('duration_minutes')
                ->label('Durasi (Menit)')
                ->state(fn (ObChecksheet $record): string => $record->duration_minutes !== null ? (string) $record->duration_minutes : '-'),

            ExportColumn::make('before_photo')
                ->label('Foto Sebelum')
                ->state(fn (ObChecksheet $record): ?string => $record->before_photo ? asset('storage/' . ltrim($record->before_photo, '/')) : null),

            ExportColumn::make('after_photo')
                ->label('Foto Sesudah')
                ->state(fn (ObChecksheet $record): ?string => $record->after_photo ? asset('storage/' . ltrim($record->after_photo, '/')) : null),

            ExportColumn::make('notes')
                ->label('Catatan'),

            ExportColumn::make('created_at')
                ->label('Tanggal Dibuat')
                ->state(fn (ObChecksheet $record): ?string => $record->created_at?->format('d/m/Y H:i:s')),
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
        $user = auth()->user();

        $query = $query->with(['user']);

        if ($user && ! $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            $query->where('user_id', $user->id);
        }

        return $query;
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
        return 'export_ob_checksheet_' . now()->format('Y-m-d_His');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Export OB Checksheet selesai: ' . Number::format($export->successful_rows) . ' baris berhasil diexport.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' baris gagal.';
        }

        return $body;
    }
}
