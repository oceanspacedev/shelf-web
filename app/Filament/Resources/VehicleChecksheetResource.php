<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleChecksheetResource\Pages;
use App\Models\AssetAttribute;
use App\Models\VehicleChecksheet;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class VehicleChecksheetResource extends Resource
{
    protected static ?string $model = VehicleChecksheet::class;

    public static function getModelLabel(): string
    {
        return __('Vehicle Checksheet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vehicle Checksheets');
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // Informasi Kendaraan
                Section::make('Informasi Kendaraan')
                    ->schema([
                        // Forms\Components\Select::make('asset_id')
                        //     ->relationship('asset', 'name')
                        //     ->label('Nama Aset'),
                        Forms\Components\TextInput::make('reference_number')
                            ->required()
                            ->maxLength(255)
                            ->label('Nomor Referensi')
                            ->readOnly()
                            ->default(function () {
                                return VehicleChecksheetResource::generateReferenceNumber();
                            }),
                        Forms\Components\Select::make('license_plate')
                            ->label('Plat Nomor')
                            ->options(function () {
                                // Ambil data dari AssetAttribute yang terkait dengan CustomAssetAttribute "Plat Nomor"
                                return AssetAttribute::whereHas('customAttribute', function ($query) {
                                    $query->where('name', 'Plat Nomor');
                                })->pluck('attribute_value', 'attribute_value'); // Menggunakan attribute_value sebagai key dan value
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('Pilih Plat Nomor'),
                        Forms\Components\TextInput::make('pic')
                            ->maxLength(255)
                            ->label('PIC (Penanggung Jawab)')
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255)
                            ->label('Lokasi Kendaraan')
                            ->placeholder('Contoh: Depo 1, Workshop, dll.')
                            ->required(),
                        Forms\Components\TextInput::make('destination')
                            ->maxLength(255)
                            ->label('Tujuan')
                            ->placeholder('Contoh: Depo 1, Workshop, dll.')
                            ->required(),
                    ]),

                // Informasi Keberangkatan
                Section::make('Informasi Keberangkatan')
                    ->schema([
                        Forms\Components\TextInput::make('start_km')
                            ->required()
                            ->numeric()
                            ->label('Kilometer Awal')
                            ->placeholder('Masukkan KM awal'),
                        Forms\Components\DateTimePicker::make('departure_time')
                            ->required()
                            ->label('Waktu Keberangkatan')
                            ->default(now()),
                        Forms\Components\FileUpload::make('departure_photo')
                            ->required()
                            ->label('Foto Keberangkatan')
                            ->disk('public')
                            ->directory('vehiclechecksheet')
                            ->previewable()
                            ->imagePreviewHeight('250')
                            ->visibility('public'),
                        Forms\Components\FileUpload::make('departure_damage_report')
                            ->required()
                            ->label('Laporan Kerusakan Saat Keberangkatan')
                            ->disk('public')
                            ->directory('vehiclechecksheet')
                            ->previewable()
                            ->imagePreviewHeight('250')
                            ->visibility('public'),
                    ]),

                // Informasi Pengembalian
                Section::make('Informasi Pengembalian')
                    ->schema([
                        Forms\Components\TextInput::make('end_km')
                            ->numeric()
                            ->label('Kilometer Akhir')
                            ->placeholder('Masukkan KM akhir'),
                        Forms\Components\DateTimePicker::make('return_time')
                            ->label('Waktu Pengembalian'),
                        Forms\Components\FileUpload::make('return_photo')
                            ->label('Foto Pengembalian')
                            ->disk('public')
                            ->directory('vehiclechecksheet')
                            ->previewable()
                            ->imagePreviewHeight('250')
                            ->visibility('public'),
                        Forms\Components\FileUpload::make('return_damage_report')
                            ->label('Laporan Kerusakan Saat Pengembalian')
                            ->disk('public')
                            ->directory('vehiclechecksheet')
                            ->previewable()
                            ->imagePreviewHeight('250')
                            ->visibility('public'),
                    ])
                    ->hidden(fn ($livewire) => $livewire instanceof CreateRecord),
                // Informasi Tambahan
                Section::make('Informasi Tambahan')
                    ->schema([
                        Forms\Components\TextInput::make('rental_duration')
                            ->numeric()
                            ->label('Durasi Sewa (jam)')
                            ->disabled(), // Set as read-only
                        Forms\Components\TextInput::make('distance_traveled')
                            ->numeric()
                            ->default(0.00)
                            ->label('Jarak Tempuh')
                            ->disabled(), // Set as read-only
                        Forms\Components\Textarea::make('remarks')
                            ->columnSpanFull()
                            ->label('Catatan Tambahan'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->searchable()
                    ->label('Nomor Referensi')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('pic')
                    ->searchable()
                    ->label('PIC')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('license_plate')
                    ->searchable()
                    ->label('Plat Nomor')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->searchable()
                    ->label('Lokasi')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('destination')
                    ->searchable()
                    ->label('Tujuan')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('start_km')
                    ->numeric()
                    ->label('Kilometer Awal')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('departure_time')
                    ->dateTime()
                    ->sortable()
                    ->label('Waktu Keberangkatan')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('departure_photo')
                    ->checkFileExistence(false)
                    ->label('Foto Keberangkatan')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('departure_damage_report')
                    ->checkFileExistence(false)
                    ->label('Laporan Kerusakan Keberangkatan')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('end_km')
                    ->numeric()
                    ->label('Kilometer Akhir')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('return_time')
                    ->dateTime()
                    ->label('Waktu Kembali')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('return_photo')
                    ->checkFileExistence(false)
                    ->label('Foto Kembali')
                    ->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('return_damage_report')
                    ->checkFileExistence(false)
                    ->label('Laporan Kerusakan Kembali')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('rental_duration')
                    ->numeric()
                    ->label('Durasi Sewa')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('distance_traveled')
                    ->numeric()
                    ->label('Jarak Tempuh')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Tanggal Dibuat'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Tanggal Diperbarui'),
            ])
            ->filters([
                SelectFilter::make('license_plate')
                    ->label('Plat Nomor')
                    ->options(fn () => VehicleChecksheet::query()
                        ->orderBy('license_plate')
                        ->distinct()
                        ->pluck('license_plate', 'license_plate')
                        ->all()),
                SelectFilter::make('pic')
                    ->label('PIC')
                    ->options(fn () => VehicleChecksheet::query()
                        ->orderBy('pic')
                        ->distinct()
                        ->pluck('pic', 'pic')
                        ->all()),
                SelectFilter::make('location')
                    ->label('Lokasi')
                    ->options(fn () => VehicleChecksheet::query()
                        ->orderBy('location')
                        ->distinct()
                        ->pluck('location', 'location')
                        ->all()),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->actions([
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->visible(fn () => auth()->user()->can('export', static::getModel()))
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns([
                                    Column::make('reference_number')->heading('Nomor Referensi'),
                                    Column::make('license_plate')->heading('Plat Nomor'),
                                    Column::make('pic')->heading('PIC'),
                                    Column::make('location')->heading('Lokasi'),
                                    Column::make('destination')->heading('Tujuan'),
                                    Column::make('start_km')->heading('Kilometer Awal'),
                                    Column::make('departure_time')->heading('Waktu Keberangkatan'),
                                    Column::make('departure_photo')
                                        ->heading('Foto Keberangkatan')
                                        ->getStateUsing(fn ($record) => $record->departure_photo ? Storage::disk('public')->url($record->departure_photo) : null),
                                    Column::make('departure_damage_report')
                                        ->heading('Laporan Kerusakan Keberangkatan')
                                        ->getStateUsing(fn ($record) => $record->departure_damage_report ? Storage::disk('public')->url($record->departure_damage_report) : null),
                                    Column::make('end_km')->heading('Kilometer Akhir'),
                                    Column::make('return_time')->heading('Waktu Pengembalian'),
                                    Column::make('return_photo')
                                        ->heading('Foto Pengembalian')
                                        ->getStateUsing(fn ($record) => $record->return_photo ? Storage::disk('public')->url($record->return_photo) : null),
                                    Column::make('return_damage_report')
                                        ->heading('Laporan Kerusakan Pengembalian')
                                        ->getStateUsing(fn ($record) => $record->return_damage_report ? Storage::disk('public')->url($record->return_damage_report) : null),
                                    Column::make('rental_duration')->heading('Durasi Sewa'),
                                    Column::make('distance_traveled')->heading('Jarak Tempuh'),
                                    Column::make('remarks')->heading('Catatan Tambahan'),
                                    Column::make('created_at')->heading('Tanggal Dibuat'),
                                    Column::make('updated_at')->heading('Tanggal Diperbarui'),
                                ])
                                ->withFilename('export_vehicle_checksheet_'.date('Y-m-d')),
                        ]),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->orderBy('created_at', 'desc');
    }

    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        // Panggil fungsi generateReferenceNumber untuk menghasilkan nomor referensi
        $data['reference_number'] = self::generateReferenceNumber();

        return $data;
    }

    protected static function generateReferenceNumber(): string
    {
        $year = date('Y');

        // Cari record dengan nomor terbesar untuk tahun ini
        $latestRecord = VehicleChecksheet::whereYear('created_at', $year)
            ->orderByRaw('CAST(SUBSTRING_INDEX(reference_number, "-", -1) AS UNSIGNED) DESC')
            ->first();

        if ($latestRecord) {
            // Ambil nomor urut terakhir dan tambahkan 1
            $lastNumber = (int) Str::afterLast($latestRecord->reference_number, '-');
            $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            // Jika belum ada record untuk tahun ini, mulai dari 001
            $newNumber = '001';
        }

        // Format akhir menjadi GA-{tahun}-{nomor urut tiga digit}
        return "GA-{$year}-{$newNumber}";
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVehicleChecksheets::route('/'),
            'create' => Pages\CreateVehicleChecksheet::route('/create'),
            'edit' => Pages\EditVehicleChecksheet::route('/{record}/edit'),
        ];
    }
}
