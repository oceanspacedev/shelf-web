<?php

namespace App\Filament\Resources\AssetRequestResource\Pages;

use App\Enums\AssetRequestType;
use App\Filament\Resources\AssetRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListAssetRequests extends ListRecords
{
    protected static string $resource = AssetRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Export')
                ->icon('heroicon-o-document-arrow-up')
                ->color('warning')
                ->visible(fn () => auth()->user()->can('export', static::$resource::getModel()))
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withColumns([
                            Column::make('reference_number')->heading('Nomor Referensi'),
                            Column::make('type')
                                ->heading('Jenis Pengajuan')
                                ->getStateUsing(fn ($record) => $record->type instanceof AssetRequestType ? $record->type->label() : ucfirst((string) $record->type)),
                            Column::make('user.name')->heading('Nama Pemohon'),
                            Column::make('division.name')->heading('Divisi'),
                            Column::make('item_name')
                                ->heading('Nama Aset')
                                ->getStateUsing(fn ($record) => $record->type === AssetRequestType::Pengadaan ? $record->item_name : ($record->asset?->name ?? $record->item_name)),
                            Column::make('qty')
                                ->heading('Jumlah')
                                ->getStateUsing(fn ($record) => $record->type === AssetRequestType::Pengadaan ? $record->qty : 1),
                            Column::make('status')
                                ->heading('Status')
                                ->getStateUsing(fn ($record) => $record->status?->label()),
                            Column::make('lifecycle_stage_label')
                                ->heading('Tahap Lifecycle')
                                ->getStateUsing(fn ($record) => $record->lifecycleStageLabel()),
                            Column::make('next_step_label')
                                ->heading('Langkah Berikutnya')
                                ->getStateUsing(fn ($record) => $record->nextStepLabel()),
                            Column::make('attachment')
                                ->heading('Lampiran')
                                ->getStateUsing(function ($record) {
                                    if (empty($record->attachment)) {
                                        return '-';
                                    }
                                    $attachments = is_array($record->attachment) ? $record->attachment : [$record->attachment];

                                    return collect($attachments)
                                        ->map(fn ($file) => Storage::disk('public')->url($file))
                                        ->implode(', ');
                                }),
                            Column::make('description')->heading('Keterangan'),
                            Column::make('notes')->heading('Catatan Admin'),
                            Column::make('created_at')->heading('Tanggal Dibuat'),
                            Column::make('updated_at')->heading('Tanggal Diperbarui'),
                        ])
                        ->withFilename('export_asset_requests_'.date('Y-m-d')),
                ]),
            Actions\CreateAction::make(),
        ];
    }
}
