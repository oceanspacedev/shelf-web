<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomAssetAttributeResource\Pages;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomAssetAttributeResource extends Resource
{
    protected static ?string $model = CustomAssetAttribute::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getCategoryOptions()
    {
        $categories = Category::with('children')->get();

        $options = [];
        foreach ($categories as $category) {
            if ($category->children->isNotEmpty()) {
                $subcategories = $category->children->pluck('name', 'id')->toArray();
                $options[$category->name] = $subcategories;
            }
        }

        return $options;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Informasi dasar
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Atribut')
                            ->placeholder('Masukkan nama atribut')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('Tipe Input')
                            ->required()
                            ->options([
                                'text' => 'Text Input',
                                'number' => 'Number Input',
                                'textarea' => 'Textarea',
                                'date' => 'Date Picker',
                            ])
                            ->searchable()
                            ->placeholder('Pilih tipe input')
                            ->reactive(),
                    ])
                    ->columns(2), // Membagi dua kolom untuk section ini

                // Status Atribut
                Forms\Components\Section::make('Status Atribut')
                    ->schema([
                        Forms\Components\Toggle::make('required')
                            ->label('Wajib Diisi')
                            ->inline(false)
                            ->default(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktif')
                            ->inline(false)
                            ->default(true),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategori')
                            ->options(self::getCategoryOptions())
                            ->multiple()
                            ->searchable()
                            ->placeholder('Pilih kategori yang relevan')
                            ->afterStateHydrated(function ($state, callable $set) {
                                if ($state) {
                                    $set('category_id', array_map('intval', $state)); // Konversi ke integer saat dihydrate
                                }
                            }),

                    ])
                    ->columns(3),

                // Pengaturan Notifikasi
                Forms\Components\Section::make('Pengaturan Notifikasi')
                    ->schema([
                        Forms\Components\Toggle::make('is_notifiable')
                            ->label('Mengaktifkan Notifikasi')
                            ->inline(false)
                            ->default(false)
                            ->helperText('Aktifkan untuk menerima notifikasi.')
                            ->reactive(),

                        Forms\Components\Select::make('notification_type')
                            ->label('Tipe Notifikasi')
                            ->options([
                                'fixed_date' => 'Fixed Date',
                                'relative_date' => 'Relative Date',
                            ])
                            ->placeholder('Pilih tipe notifikasi')
                            ->helperText('Jenis notifikasi yang akan dikirimkan.')
                            ->required()
                            ->reactive()
                            ->visible(fn (callable $get) => $get('is_notifiable')), // Pastikan ini reactive agar perubahan langsung mempengaruhi elemen lainnya

                        // Pengaturan yang akan tampil jika 'relative_date' dipilih
                        Forms\Components\TextInput::make('notification_offset')
                            ->label('Offset Notifikasi')
                            ->placeholder('Masukkan offset notifikasi (dalam hari)')
                            ->numeric()
                            ->helperText('Jumlah hari sebelum notifikasi dikirim.')
                            ->visible(fn (callable $get) => $get('notification_type') === 'relative_date'),

                        // Pengaturan yang akan tampil jika 'fixed_date' dipilih
                        Forms\Components\DatePicker::make('fixed_notification_date')
                            ->label('Tanggal Notifikasi Tetap')
                            ->placeholder('Pilih tanggal tetap untuk notifikasi')
                            ->visible(fn (callable $get) => $get('notification_type') === 'fixed_date'),
                    ])
                    ->visible(fn (callable $get) => $get('type') === 'date')
                    ->columns(3)
                    ->collapsed(false), // Section ini tetap terbuka

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('required')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('category_id')
                    ->label('Kategori')
                    ->colors([
                        'primary', // Warna utama untuk semua badge, dapat disesuaikan berdasarkan kebutuhan
                    ])
                    ->formatStateUsing(function ($state) {
                        // Jika category_id menyimpan ID kategori, ubah menjadi nama kategori
                        $categories = Category::whereIn('id', is_array($state) ? $state : [$state])->pluck('name')->toArray();

                        return implode(', ', $categories); // Menggabungkan nama kategori dengan koma jika ada lebih dari satu
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_notifiable')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notification_type')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notification_offset')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fixed_notification_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipe Input')
                    ->options([
                        'text' => 'Text Input',
                        'number' => 'Number Input',
                        'textarea' => 'Textarea',
                        'date' => 'Date Picker',
                    ]),
                Tables\Filters\TernaryFilter::make('required')
                    ->label('Wajib Diisi'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Aktif'),
                Tables\Filters\TernaryFilter::make('is_notifiable')
                    ->label('Notifikasi'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomAssetAttributes::route('/'),
            'create' => Pages\CreateCustomAssetAttribute::route('/create'),
            'edit' => Pages\EditCustomAssetAttribute::route('/{record}/edit'),
        ];
    }
}
