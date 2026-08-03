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
                TextColumn::make('external_location_code')->label('Gudang')->badge()->searchable(),
                TextColumn::make('external_business_entity_code')
                    ->label('Marker Badan Usaha CSA')
                    ->badge()
                    ->placeholder('Default')
                    ->toggleable(),
                TextColumn::make('businessEntity.name')
                    ->label('Target Badan Usaha')
                    ->badge()
                    ->placeholder('Belum dipetakan')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('external_item_code')->label('Kode Item')->searchable()->placeholder('-'),
                TextColumn::make('item_name')->label('Barang / Item')->searchable()->wrap(),
                TextColumn::make('serial_number')->label('S/N / IMEI')->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('system_qty')->label('CSA Sistem')->numeric()->placeholder('-'),
                TextColumn::make('physical_qty')->label('Audit Fisik')->numeric()->placeholder('-'),
                TextColumn::make('correction_qty')->label('Koreksi')->numeric()->placeholder('-'),
                TextColumn::make('shelf_qty')->label('Shelf Saat Ini')->numeric()->placeholder('-'),
                TextColumn::make('target_qty')->label('Target Shelf')->numeric(),
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
                        'create' => 'Buat Aset',
                        'adjust' => 'Sesuaikan',
                        'retire' => 'Saldo 0',
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
                        'adjust' => 'Sesuaikan',
                        'retire' => 'Saldo 0',
                        'review' => 'Tinjau Manual',
                        'none' => 'Tidak Ada Aksi',
                    ]),
            ])
            ->emptyStateHeading('Belum ada baris laporan')
            ->emptyStateDescription('Import workbook audit CSA untuk menghasilkan banding Inline/Gap/Blocked.');
    }
}
