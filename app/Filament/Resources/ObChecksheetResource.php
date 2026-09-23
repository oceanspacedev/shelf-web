<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ObChecksheetExporter;
use App\Filament\Resources\ObChecksheetResource\Pages;
use App\Models\ObChecksheet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ObChecksheetResource extends Resource
{
    protected static ?string $model = ObChecksheet::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function getModelLabel(): string
    {
        return __('OB Checksheet');
    }

    public static function getPluralModelLabel(): string
    {
        return __('OB Checksheets');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                // Field otomatis yang tersimpan di latar belakang
                \Filament\Forms\Components\Hidden::make('user_id')
                    ->default(fn () => auth()->id()),

                \Filament\Forms\Components\Hidden::make('started_at')
                    ->default(now()),

                \Filament\Forms\Components\Hidden::make('cleaned_at')
                    ->default(now()),

                // Hanya tampil di halaman detail / edit admin untuk kebutuhan pelacakan & edit penuh
                Section::make('Informasi Laporan')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                            ->schema([
                                TextInput::make('reference_number')
                                    ->label('Nomor Referensi')
                                    ->readOnly(),

                                Select::make('user_id')
                                    ->label('Petugas OB')
                                    ->relationship('user', 'name')
                                    ->searchable(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'in_progress' => 'Sedang Dikerjakan',
                                        'completed' => 'Selesai',
                                    ])
                                    ->required(),

                                DateTimePicker::make('started_at')
                                    ->label('Waktu Mulai')
                                    ->seconds(false),

                                DateTimePicker::make('finished_at')
                                    ->label('Waktu Selesai')
                                    ->seconds(false),

                                TextInput::make('duration_minutes')
                                    ->label('Durasi')
                                    ->suffix('menit')
                                    ->numeric(),
                            ]),
                    ])
                    ->hiddenOn('create'),

                Section::make('Ruangan yang Dibersihkan')
                    ->schema([
                        TextInput::make('room')
                            ->label('Nama Ruangan')
                            ->placeholder('Ketik nama ruangan (contoh: Toilet Lt. 1, Pantry, Lobby, Ruang Rapat)')
                            ->datalist(function () {
                                $user = auth()->user();
                                if (! $user) {
                                    return [];
                                }

                                $query = ObChecksheet::query()
                                    ->whereNotNull('room')
                                    ->where('room', '!=', '');

                                if (! $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
                                    return $query->where('user_id', $user->id)
                                        ->distinct()
                                        ->pluck('room')
                                        ->toArray();
                                }

                                return $query->distinct()->pluck('room')->toArray();
                            })
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Dokumentasi Foto Kebersihan')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        \App\Forms\Components\CameraCapture::make('before_photo')
                            ->label('Foto Sebelum Pembersihan')
                            ->folder('ob-checksheets/before')
                            ->required(),

                        \App\Forms\Components\CameraCapture::make('after_photo')
                            ->label('Foto Sesudah Pembersihan')
                            ->folder('ob-checksheets/after')
                            ->hiddenOn('create'),
                    ]),

                Section::make('Catatan Tambahan (Opsional)')
                    ->collapsible()
                    ->collapsed(fn ($operation) => $operation === 'create')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->placeholder('Tulis catatan kondisi ruangan jika ada hal khusus...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_number')
                    ->label('No. Referensi')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'warning' => 'in_progress',
                        'success' => 'completed',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'in_progress' => 'Sedang Dikerjakan',
                        'completed' => 'Selesai',
                        default => $state ?? '-',
                    })
                    ->sortable(),

                TextColumn::make('room')
                    ->label('Ruangan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (ObChecksheet $record) => $record->started_at?->format('d M H:i') . ($record->duration_minutes !== null ? " ({$record->duration_minutes} mnt)" : ($record->status === 'in_progress' ? ' • Berjalan...' : '')))
                    ->wrap(),

                ImageColumn::make('before_photo')
                    ->label('Sebelum')
                    ->disk('public')
                    ->checkFileExistence(false)
                    ->square()
                    ->size(40),

                ImageColumn::make('after_photo')
                    ->label('Sesudah')
                    ->disk('public')
                    ->checkFileExistence(false)
                    ->square()
                    ->size(40)
                    ->placeholder('-'),

                TextColumn::make('started_at')
                    ->label('Waktu Mulai')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('Petugas OB')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('finished_at')
                    ->label('Waktu Selesai')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('duration_minutes')
                    ->label('Durasi')
                    ->suffix(' mnt')
                    ->placeholder('Berjalan...')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Pembersihan')
                    ->options([
                        'in_progress' => 'Sedang Dikerjakan',
                        'completed' => 'Selesai',
                    ]),

                SelectFilter::make('room')
                    ->label('Ruangan')
                    ->options(function () {
                        $user = auth()->user();
                        if (! $user) {
                            return [];
                        }

                        $query = ObChecksheet::query()
                            ->whereNotNull('room')
                            ->where('room', '!=', '');

                        if (! $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
                            $query->where('user_id', $user->id);
                        }

                        return $query->distinct()->pluck('room', 'room')->toArray();
                    }),

                SelectFilter::make('user_id')
                    ->label('Petugas OB')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()?->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])),
            ])
            ->actions([
                Action::make('complete')
                    ->label('Foto Sesudah')
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->modalWidth('lg')
                    ->visible(function (ObChecksheet $record) {
                        $user = auth()->user();
                        $canAccess = $user?->hasRole(['super_admin', 'admin', 'general_affair']) || $record->user_id === $user?->id;

                        return $canAccess && ($record->status === 'in_progress' || blank($record->after_photo));
                    })
                    ->modalHeading(fn (ObChecksheet $record) => 'Selesaikan Pembersihan: ' . $record->room)
                    ->modalSubmitActionLabel('Simpan & Selesaikan')
                    ->form([
                        \App\Forms\Components\CameraCapture::make('after_photo')
                            ->label('Foto Sesudah Pembersihan')
                            ->folder('ob-checksheets/after')
                            ->columnSpanFull()
                            ->required(),
                        \Filament\Forms\Components\Textarea::make('notes')
                            ->label('Catatan Tambahan (Opsional)')
                            ->placeholder('Tulis catatan jika ada...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (ObChecksheet $record, array $data) {
                        $record->markAsCompleted($data['after_photo'], $data['notes'] ?? null);

                        \Filament\Notifications\Notification::make()
                            ->title('Pembersihan Selesai!')
                            ->body("Ruangan {$record->room} berhasil diselesaikan (durasi: {$record->duration_minutes} menit).")
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray'),
            ], position: RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ObChecksheetExporter::class)
                        ->label('Export Dipilih')
                        ->visible(fn () => auth()->user()?->can('export', ObChecksheet::class) ?? false),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Pengawas / Admin dapat melihat seluruh checksheet dari semua OB
        if ($user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            return $query;
        }

        // Petugas OB hanya melihat data checksheet miliknya sendiri
        return $query->where('user_id', $user->id);
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
            'index' => Pages\ListObChecksheets::route('/'),
            'create' => Pages\CreateObChecksheet::route('/create'),
            'edit' => Pages\EditObChecksheet::route('/{record}/edit'),
        ];
    }
}
