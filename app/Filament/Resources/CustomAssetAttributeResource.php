<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomAssetAttributeResource\Pages;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CustomAssetAttributeResource extends Resource
{
    protected static ?string $model = CustomAssetAttribute::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

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
                            ->options(CustomAssetAttribute::typeOptions())
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
                            ->label('Aktifkan Pengingat')
                            ->inline(false)
                            ->default(false)
                            ->helperText('Kirim pengingat harian saat dokumen atau tanggal atribut masuk masa pembaruan.')
                            ->reactive(),

                        Forms\Components\Select::make('notification_type')
                            ->label('Pola Pengingat')
                            ->options([
                                'relative_date' => 'Harian sebelum tanggal berlaku habis',
                                'fixed_date' => 'Tanggal tetap',
                            ])
                            ->default('relative_date')
                            ->placeholder('Pilih pola pengingat')
                            ->helperText('Untuk dokumen masa berlaku, gunakan pola harian sebelum tanggal berlaku habis.')
                            ->required()
                            ->reactive()
                            ->visible(fn (callable $get) => $get('is_notifiable')), // Pastikan ini reactive agar perubahan langsung mempengaruhi elemen lainnya

                        // Pengaturan yang akan tampil jika 'relative_date' dipilih
                        Forms\Components\TextInput::make('notification_offset')
                            ->label('Mulai Pengingat H-')
                            ->placeholder('Contoh: 30, 14, 7')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Notifikasi muncul setiap hari mulai H-ini sampai tanggal/lampiran diperbarui.')
                            ->visible(fn (callable $get) => $get('notification_type') === 'relative_date'),

                        // Pengaturan yang akan tampil jika 'fixed_date' dipilih
                        Forms\Components\DatePicker::make('fixed_notification_date')
                            ->label('Tanggal Notifikasi Tetap')
                            ->placeholder('Pilih tanggal tetap untuk notifikasi')
                            ->visible(fn (callable $get) => $get('notification_type') === 'fixed_date'),

                        Forms\Components\CheckboxList::make('notification_channels')
                            ->label('Kirim Lewat')
                            ->options(CustomAssetAttribute::notificationChannelOptions())
                            ->default([CustomAssetAttribute::CHANNEL_WHATSAPP])
                            ->columns(2)
                            ->helperText('Pilih satu atau lebih channel pengingat.')
                            ->visible(fn (callable $get) => $get('is_notifiable')),

                        Forms\Components\Select::make('notification_recipient_user_ids')
                            ->label('Penerima Internal')
                            ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Email akan dikirim ke email user yang dipilih. Nomor WhatsApp bisa ditambahkan di field nomor WhatsApp.')
                            ->visible(fn (callable $get) => $get('is_notifiable'))
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('notification_recipient_emails')
                            ->label('Email Tambahan')
                            ->placeholder('admin@example.com')
                            ->helperText('Opsional. Gunakan untuk penerima di luar user internal.')
                            ->visible(fn (callable $get) => $get('is_notifiable')
                                && in_array(CustomAssetAttribute::CHANNEL_EMAIL, $get('notification_channels') ?? [], true))
                            ->columnSpanFull(),

                        Forms\Components\TagsInput::make('notification_recipient_whatsapp_numbers')
                            ->label('Nomor WhatsApp Tujuan')
                            ->placeholder('628123456789')
                            ->helperText('Opsional. Jika kosong, sistem memakai DEFAULT_NOTIFICATION_PHONE sebagai fallback.')
                            ->visible(fn (callable $get) => $get('is_notifiable')
                                && in_array(CustomAssetAttribute::CHANNEL_WHATSAPP, $get('notification_channels') ?? [], true))
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (callable $get) => in_array($get('type'), [
                        CustomAssetAttribute::TYPE_DATE,
                        CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
                    ], true))
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
                Tables\Columns\TextColumn::make('notification_channels')
                    ->label('Channel')
                    ->formatStateUsing(fn (CustomAssetAttribute $record): string => collect($record->notificationChannels())
                        ->map(fn (string $channel): string => CustomAssetAttribute::notificationChannelOptions()[$channel] ?? $channel)
                        ->implode(', '))
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
                    ->options(CustomAssetAttribute::typeOptions()),
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
