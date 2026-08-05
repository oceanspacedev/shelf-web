<?php

namespace App\Filament\Resources\AssetReconciliationResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = '2. Laporan per Barang / Item';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        if (($ownerRecord->source_system ?? null) === 'VEHICLE_AUDIT') {
            return '2. Laporan per Kendaraan';
        }

        return '2. Laporan per Barang / Item';
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByRaw(
                "CASE comparison_status WHEN 'blocked' THEN 0 WHEN 'gap' THEN 1 WHEN 'inline' THEN 2 ELSE 3 END"
            )->orderBy('source_row'))
            ->columns([
                TextColumn::make('source_row')->label('Baris')->numeric()->sortable(),
                TextColumn::make('source_rows')
                    ->label('Baris Sumber')
                    ->formatStateUsing(fn (array $state): string => implode(', ', $state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('external_location_code')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Keberadaan' : 'Gudang')
                    ->badge()
                    ->searchable(),
                TextColumn::make('external_business_entity_code')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Marker ACC / Badan Usaha' : 'Marker Badan Usaha CSA')
                    ->badge()
                    ->placeholder('Default')
                    ->toggleable(),
                TextColumn::make('businessEntity.name')
                    ->label('Target Badan Usaha')
                    ->badge()
                    ->placeholder('Belum dipetakan')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('external_item_code')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Plat' : 'Kode Item')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('item_name')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Kendaraan' : 'Barang / Item')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('serial_number')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'No Rangka' : 'S/N / IMEI')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('system_qty')
                    ->label('CSA Sistem')
                    ->numeric()
                    ->placeholder('-')
                    ->visible(fn (): bool => ! $this->isVehicleAudit()),
                TextColumn::make('physical_qty')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Audit (1/0)' : 'Audit Fisik')
                    ->numeric()
                    ->placeholder('-'),
                TextColumn::make('correction_qty')
                    ->label('Koreksi')
                    ->numeric()
                    ->placeholder('-')
                    ->visible(fn (): bool => ! $this->isVehicleAudit()),
                TextColumn::make('shelf_qty')->label('Shelf Saat Ini')->numeric()->placeholder('-'),
                TextColumn::make('target_qty')
                    ->label(fn (): string => $this->isVehicleAudit() ? 'Target (1 aktif / 0 sold)' : 'Target Shelf')
                    ->numeric(),
                TextColumn::make('gap_qty')->label('Gap Shelf')->numeric()->placeholder('-'),
                TextColumn::make('comparison_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inline' => 'Inline',
                        'gap' => 'Gap',
                        'blocked' => 'Terblokir',
                        'applied' => 'Diterapkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'inline' => 'success',
                        'gap' => 'warning',
                        'blocked' => 'danger',
                        'applied' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'create', 'create_missing' => 'Buat Aset',
                        'adjust' => 'Sesuaikan',
                        'retire' => 'Saldo 0',
                        'mark_sold' => 'Tandai Terjual',
                        'retire_duplicate' => 'Nonaktifkan Duplikat',
                        'enrich' => 'Lengkapi Identitas',
                        'review' => 'Tinjau Manual',
                        'none' => 'Tidak Ada Aksi',
                        default => $state ?? '-',
                    }),
                TextColumn::make('match_strategy')->label('Pencocokan')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('candidate_asset_ids')
                    ->label('Kandidat Asset ID')
                    ->formatStateUsing(fn (?array $state): string => collect($state)->filter()->implode(', '))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('message')->label('Penjelasan')->wrap()->limit(100),
                TextColumn::make('notes')->label('Keterangan Audit')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('comparison_status')
                    ->label('Status Compare')
                    ->options([
                        'blocked' => 'Terblokir',
                        'gap' => 'Gap',
                        'inline' => 'Inline',
                        'applied' => 'Diterapkan',
                    ]),
                SelectFilter::make('action')
                    ->options([
                        'create' => 'Buat Aset',
                        'create_missing' => 'Buat Aset (Kendaraan)',
                        'adjust' => 'Sesuaikan',
                        'retire' => 'Saldo 0',
                        'mark_sold' => 'Tandai Terjual',
                        'retire_duplicate' => 'Nonaktifkan Duplikat',
                        'enrich' => 'Lengkapi Identitas',
                        'review' => 'Tinjau Manual',
                        'none' => 'Tidak Ada Aksi',
                    ]),
            ])
            ->emptyStateHeading('Belum ada baris laporan')
            ->emptyStateDescription('Import workbook audit untuk menghasilkan banding Inline/Gap/Blocked.');
    }

    private function isVehicleAudit(): bool
    {
        return ($this->getOwnerRecord()->source_system ?? null) === 'VEHICLE_AUDIT';
    }
}
