<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VehicleChecksheetResource\Pages;
use App\Models\VehicleChecksheet;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class VehicleChecksheetResource extends Resource
{
    protected static ?string $model = VehicleChecksheet::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Informasi Kendaraan
                Forms\Components\Section::make('Informasi Kendaraan')
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
                                return \App\Models\AssetAttribute::whereHas('customAttribute', function ($query) {
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
                Forms\Components\Section::make('Informasi Keberangkatan')
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
                            ->image()
                            ->resize(50)
                            ->required()
                            ->label('Foto Keberangkatan')
                            ->directory('vehiclechecksheet')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file, $record) => sprintf(
                                    'scale_%s_departure_photo_%s.%s',
                                    $record?->reference_number ?? VehicleChecksheetResource::generateReferenceNumber(),
                                    now()->format('Ymd_His'),
                                    $file->getClientOriginalExtension()
                                )
                            ),
                        Forms\Components\FileUpload::make('departure_damage_report')
                            ->image()
                            ->resize(50)
                            ->required()
                            ->label('Laporan Kerusakan Saat Keberangkatan')
                            ->directory('vehiclechecksheet')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file, $record) => sprintf(
                                    'scale_%s_departure_damage_report_%s.%s',
                                    $record?->reference_number ?? VehicleChecksheetResource::generateReferenceNumber(),
                                    now()->format('Ymd_His'),
                                    $file->getClientOriginalExtension()
                                )
                            ),
                    ]),

                // Informasi Pengembalian
                Forms\Components\Section::make('Informasi Pengembalian')
                    ->schema([
                        Forms\Components\TextInput::make('end_km')
                            ->required()
                            ->numeric()
                            ->label('Kilometer Akhir')
                            ->placeholder('Masukkan KM akhir'),
                        Forms\Components\DateTimePicker::make('return_time')
                            ->required()
                            ->label('Waktu Pengembalian'),
                        Forms\Components\FileUpload::make('return_photo')
                            ->required()
                            ->image()
                            ->resize(50)
                            ->label('Foto Pengembalian')
                            ->directory('vehiclechecksheet')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file, $record) => sprintf(
                                    'scale_%s_return_photo_%s.%s',
                                    $record?->reference_number ?? VehicleChecksheetResource::generateReferenceNumber(),
                                    now()->format('Ymd_His'),
                                    $file->getClientOriginalExtension()
                                )
                            ),
                        Forms\Components\FileUpload::make('return_damage_report')
                            ->required()
                            ->image()
                            ->resize(50)
                            ->label('Laporan Kerusakan Saat Pengembalian')
                            ->directory('vehiclechecksheet')
                            ->visibility('public')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file, $record) => sprintf(
                                    'scale_%s_return_damage_report_%s.%s',
                                    $record?->reference_number ?? VehicleChecksheetResource::generateReferenceNumber(),
                                    now()->format('Ymd_His'),
                                    $file->getClientOriginalExtension()
                                )
                            ),
                    ])
                    ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                // Informasi Tambahan
                Forms\Components\Section::make('Informasi Tambahan')
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
