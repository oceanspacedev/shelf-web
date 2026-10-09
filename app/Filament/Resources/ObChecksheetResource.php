<?php

namespace App\Filament\Resources;

use App\Filament\Exports\ObChecksheetExporter;
use App\Filament\Resources\ObChecksheetResource\Pages;
use App\Forms\Components\CameraCapture;
use App\Models\ObChecksheet;
use App\Models\User;
use App\Support\StoredFile;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),

                Hidden::make('started_at')
                    ->default(now()),

                Hidden::make('cleaned_at')
                    ->default(now()),

                // Hanya tampil di halaman detail / edit admin untuk kebutuhan pelacakan & edit penuh
                Section::make('Informasi Laporan')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'md' => 3])
                            ->schema([
                                TextInput::make('reference_number')
                                    ->label('Nomor Referensi')
                                    ->readOnly(),

                                // Hanya atasan yang boleh mengganti petugas; field nonaktif tidak ikut tersimpan.
                                Select::make('user_id')
                                    ->label('Petugas OB')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->disabled(fn (): bool => ! (auth()->user()?->can('assign', ObChecksheet::class) ?? false)),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(ObChecksheet::statusOptions())
                                    ->required(),

                                Select::make('source')
                                    ->label('Jenis')
                                    ->options(ObChecksheet::sourceOptions())
                                    ->disabled()
                                    ->dehydrated(false),

                                DatePicker::make('scheduled_date')
                                    ->label('Tanggal'),

                                TextInput::make('shift_label')
                                    ->label('Keterangan Shift')
                                    ->maxLength(255),

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

                Section::make('Ruangan yang Akan Dibersihkan')
                    ->description('Tambahkan semua ruangan dan foto sebelum sekaligus. Foto sesudah diambil nanti, satu ruangan satu kali.')
                    ->visibleOn('create')
                    ->schema([
                        Repeater::make('rooms')
                            ->label('Daftar ruangan')
                            ->schema([
                                TextInput::make('room')
                                    ->label('Nama Ruangan')
                                    ->placeholder('Ketik nama ruangan (contoh: Toilet Lt. 1, Pantry, Lobby, Ruang Rapat)')
                                    ->datalist(fn (): array => self::roomSuggestions())
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                CameraCapture::make('before_photo')
                                    ->label('Foto Sebelum Pembersihan')
                                    ->folder('ob-checksheets/before')
                                    ->required()
                                    ->columnSpanFull(),
                                Textarea::make('notes')
                                    ->label('Catatan (Opsional)')
                                    ->placeholder('Tulis catatan kondisi ruangan jika ada hal khusus...')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->minItems(1)
                            ->defaultItems(1)
                            ->addActionLabel('Tambah ruangan')
                            ->reorderable(false)
                            ->itemLabel(function (mixed $state): string {
                                $room = is_array($state) ? ($state['room'] ?? null) : null;

                                return filled($room) ? (string) $room : 'Ruangan';
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Ruangan yang Dibersihkan')
                    ->hiddenOn('create')
                    ->schema([
                        TextInput::make('room')
                            ->label('Nama Ruangan')
                            ->placeholder('Ketik nama ruangan (contoh: Toilet Lt. 1, Pantry, Lobby, Ruang Rapat)')
                            ->datalist(fn (): array => self::roomSuggestions())
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Section::make('Dokumentasi Foto Kebersihan')
                    ->hiddenOn('create')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        self::photoPreview('before_photo', 'Foto Sebelum Pembersihan', 'ob-checksheets/before')
                            ->required(fn (?ObChecksheet $record): bool => ! ($record?->isPending() ?? false)),
                        self::photoPreview('after_photo', 'Foto Sesudah Pembersihan', 'ob-checksheets/after'),
                    ]),

                Section::make('Catatan Tambahan (Opsional)')
                    ->hiddenOn('create')
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->placeholder('Tulis catatan kondisi ruangan jika ada hal khusus...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Informasi Laporan')
                    ->schema([
                        TextEntry::make('reference_number')->label('Nomor Referensi'),
                        TextEntry::make('user.name')->label('Petugas OB')->placeholder('Belum diambil'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => self::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => ObChecksheet::statusLabel($state)),
                        TextEntry::make('source')
                            ->label('Jenis')
                            ->badge()
                            ->color(fn (?string $state): string => $state === ObChecksheet::SOURCE_ASSIGNED ? 'info' : 'gray')
                            ->formatStateUsing(fn (?string $state): string => ObChecksheet::sourceLabel($state)),
                        TextEntry::make('room')->label('Ruangan'),
                        TextEntry::make('scheduled_date')->label('Tanggal')->date('d M Y')->placeholder('-'),
                        TextEntry::make('shift_label')->label('Shift')->placeholder('-'),
                        TextEntry::make('assigner.name')
                            ->label('Ditugaskan Oleh')
                            ->placeholder('-')
                            ->visible(fn (?ObChecksheet $record): bool => $record?->isAssigned() ?? false),
                        TextEntry::make('started_at')->label('Waktu Mulai')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('finished_at')->label('Waktu Selesai')->dateTime('d M Y H:i')->placeholder('-'),
                        TextEntry::make('duration_minutes')
                            ->label('Durasi')
                            ->suffix(' menit')
                            ->placeholder('-')
                            ->visible(fn (?ObChecksheet $record): bool => $record?->showsDuration() ?? true),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),
                Section::make('Dokumentasi Foto Kebersihan')
                    ->schema([
                        ImageEntry::make('before_photo')
                            ->label('Foto Sebelum Pembersihan')
                            ->getStateUsing(fn (ObChecksheet $record): ?string => filled($record->before_photo) ? StoredFile::browserUrl($record->before_photo) : null)
                            ->extraImgAttributes(self::lightboxAttributes())
                            ->height(250)
                            ->placeholder('-'),
                        ImageEntry::make('after_photo')
                            ->label('Foto Sesudah Pembersihan')
                            ->getStateUsing(fn (ObChecksheet $record): ?string => filled($record->after_photo) ? StoredFile::browserUrl($record->after_photo) : null)
                            ->extraImgAttributes(self::lightboxAttributes())
                            ->height(250)
                            ->placeholder('-'),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Tampilan kartu ringkas tanpa foto; foto dibuka dari halaman detail (klik kartu).
            ->columns([
                Stack::make([
                    // Atas: nomor referensi di kiri, status di kanan.
                    Split::make([
                        TextColumn::make('reference_number')
                            ->label('No. Referensi')
                            ->searchable()
                            ->sortable()
                            ->color('gray')
                            ->size(TextSize::ExtraSmall),
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => self::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => ObChecksheet::statusLabel($state))
                            ->sortable()
                            ->grow(false),
                    ]),

                    TextColumn::make('room')
                        ->label('Ruangan')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->description(fn (ObChecksheet $record): string => self::timeDescription($record))
                        ->wrap(),

                    Split::make([
                        TextColumn::make('source')
                            ->label('Jenis')
                            ->badge()
                            ->color(fn (?string $state): string => $state === ObChecksheet::SOURCE_ASSIGNED ? 'info' : 'gray')
                            ->formatStateUsing(fn (?string $state): string => ObChecksheet::sourceLabel($state))
                            ->sortable()
                            ->grow(false),
                        TextColumn::make('shift_label')
                            ->label('Shift')
                            ->badge()
                            ->color('gray')
                            ->grow(false),
                    ])->extraAttributes(['style' => 'flex-wrap: wrap;']),

                    // Bawah: siapa dan kapan dalam satu baris.
                    Split::make([
                        TextColumn::make('user.name')
                            ->label('Petugas OB')
                            ->icon('heroicon-m-user')
                            ->placeholder('Belum diambil')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('scheduled_date')
                            ->label('Tanggal')
                            ->icon('heroicon-m-calendar-days')
                            ->date('d M Y')
                            ->sortable()
                            ->grow(false),
                    ]),

                    TextColumn::make('notes')
                        ->label('Catatan')
                        ->color('gray')
                        ->limit(60),
                ])->space(2),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('scheduled_date')->orderByDesc('id'))
            ->filters([
                Filter::make('scheduled_date')
                    ->label('Tanggal')
                    ->schema([
                        DatePicker::make('from')->label('Dari Tanggal'),
                        DatePicker::make('until')->label('Sampai Tanggal'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', '<=', $date)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari '.Carbon::parse($data['from'])->format('d M Y');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai '.Carbon::parse($data['until'])->format('d M Y');
                        }

                        return $indicators;
                    }),

                SelectFilter::make('status')
                    ->label('Status Pembersihan')
                    ->options(ObChecksheet::statusOptions()),

                SelectFilter::make('source')
                    ->label('Jenis')
                    ->options(ObChecksheet::sourceOptions()),

                Filter::make('unclaimed')
                    ->label('Belum diambil OB')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->unclaimed()),

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

                        if (! $user->can('viewAll', ObChecksheet::class)) {
                            $query->where('user_id', $user->id);
                        }

                        return $query->distinct()->pluck('room', 'room')->toArray();
                    }),

                SelectFilter::make('user_id')
                    ->label('Petugas OB')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => auth()->user()?->can('viewAll', ObChecksheet::class) ?? false),
            ])
            ->actions([
                Action::make('start')
                    ->label('Foto Sebelum')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->modalWidth('lg')
                    ->visible(fn (ObChecksheet $record): bool => $record->isPending() && self::canStart($record))
                    ->modalHeading(fn (ObChecksheet $record) => 'Mulai Pembersihan: '.$record->room)
                    ->modalSubmitActionLabel('Simpan & Mulai')
                    ->form([
                        CameraCapture::make('before_photo')
                            ->label('Foto Sebelum Pembersihan')
                            ->folder('ob-checksheets/before')
                            ->columnSpanFull()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan (Opsional)')
                            ->placeholder('Tulis catatan kondisi ruangan jika ada hal khusus...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (ObChecksheet $record, array $data) {
                        // Tugas kolam menjadi milik OB yang lebih dulu mengirim foto sebelum.
                        if ($record->isUnclaimed() && ! $record->claim(auth()->user())) {
                            $record->loadMissing('user');

                            Notification::make()
                                ->title('Ruangan sudah diambil')
                                ->body("{$record->room} sudah diambil oleh {$record->user?->name}.")
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->startWork(self::photoPath($data['before_photo']), filled($data['notes'] ?? null) ? $data['notes'] : null);

                        Notification::make()
                            ->title('Pembersihan Dimulai')
                            ->body("Ruangan {$record->room} sedang dikerjakan. Ambil Foto Sesudah setelah selesai.")
                            ->success()
                            ->send();
                    }),

                Action::make('complete')
                    ->label('Foto Sesudah')
                    ->icon('heroicon-o-camera')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->modalWidth('lg')
                    ->visible(fn (ObChecksheet $record): bool => ! $record->isPending()
                        && self::canWorkOn($record)
                        && ($record->status === ObChecksheet::STATUS_IN_PROGRESS || blank($record->after_photo)))
                    ->modalHeading(fn (ObChecksheet $record) => 'Selesaikan Pembersihan: '.$record->room)
                    ->modalSubmitActionLabel('Simpan & Selesaikan')
                    ->form([
                        CameraCapture::make('after_photo')
                            ->label('Foto Sesudah Pembersihan')
                            ->folder('ob-checksheets/after')
                            ->columnSpanFull()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Tambahan (Opsional)')
                            ->placeholder('Tulis catatan jika ada...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (ObChecksheet $record, array $data) {
                        $record->markAsCompleted(self::photoPath($data['after_photo']), $data['notes'] ?? null);

                        Notification::make()
                            ->title('Pembersihan Selesai!')
                            ->body($record->showsDuration()
                                ? "Ruangan {$record->room} berhasil diselesaikan (durasi: {$record->duration_minutes} menit)."
                                : "Ruangan {$record->room} berhasil diselesaikan.")
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Atasan memindahkan tugas yang belum selesai ke OB lain (mis. petugas berhalangan).
                    BulkAction::make('reassign')
                        ->label('Ganti Petugas')
                        ->icon('heroicon-o-arrows-right-left')
                        ->visible(fn (): bool => auth()->user()?->can('assign', ObChecksheet::class) ?? false)
                        ->form([
                            Select::make('user_id')
                                ->label('Petugas Baru')
                                ->placeholder('Kembalikan ke kolam (semua OB)')
                                ->helperText('Kosongkan untuk mengembalikan tugas yang belum dimulai ke kolam bersama.')
                                ->options(fn (): array => self::officeBoyOptions())
                                ->searchable(),
                        ])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data): void {
                            if (! (auth()->user()?->can('assign', ObChecksheet::class) ?? false)) {
                                return;
                            }

                            $newOwner = filled($data['user_id'] ?? null) ? (int) $data['user_id'] : null;

                            if ($newOwner !== null && ! array_key_exists($newOwner, self::officeBoyOptions())) {
                                return;
                            }

                            // Yang sudah selesai tidak dipindah. Tugas yang sudah dimulai juga tidak
                            // bisa kembali ke kolam karena fotonya milik OB yang mengerjakannya.
                            $movable = $records->reject(fn (ObChecksheet $record): bool => $record->status === ObChecksheet::STATUS_COMPLETED
                                || ($newOwner === null && ! $record->isPending()));
                            $skipped = $records->count() - $movable->count();

                            $movable->each(fn (ObChecksheet $record) => $record->update([
                                'user_id' => $newOwner,
                                'source' => $newOwner === null ? ObChecksheet::SOURCE_ASSIGNED : $record->source,
                            ]));

                            Notification::make()
                                ->title($movable->count().' tugas dipindahkan')
                                ->body($skipped > 0 ? "{$skipped} tugas tidak dipindahkan (sudah selesai atau sudah dimulai)." : null)
                                ->success()
                                ->send();
                        }),
                    ExportBulkAction::make()
                        ->exporter(ObChecksheetExporter::class)
                        ->label('Export Dipilih')
                        ->visible(fn () => auth()->user()?->can('export', ObChecksheet::class) ?? false),
                    // Cek tiap record ke policy delete: data milik user lain butuh izin "Hapus Data Semua User".
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
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
        if ($user->can('viewAll', ObChecksheet::class)) {
            return $query;
        }

        // Petugas OB melihat checksheet miliknya dan tugas kolam bersama yang belum diambil
        return $query->visibleTo($user);
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
            'view' => Pages\ViewObChecksheet::route('/{record}'),
            'edit' => Pages\EditObChecksheet::route('/{record}/edit'),
        ];
    }

    /**
     * Atribut yang membuat gambar bisa diklik untuk diperbesar (lightbox di halaman OB Checksheet).
     *
     * @return array<string, string>
     */
    public static function lightboxAttributes(): array
    {
        return [
            'data-lightbox' => 'true',
            'title' => 'Klik untuk memperbesar',
            'style' => 'cursor: zoom-in;',
        ];
    }

    /**
     * Petugas yang bisa diberi tugas: pengguna dengan role office_boy.
     *
     * @return array<int, string>
     */
    public static function officeBoyOptions(): array
    {
        return User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', 'office_boy'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * CameraCapture menyimpan path string, tetapi state bisa berupa array bila field lain berbagi path-nya.
     */
    public static function photoPath(mixed $photo): string
    {
        if (is_array($photo)) {
            $photo = collect($photo)->first(fn (mixed $value): bool => is_string($value) && $value !== '');
        }

        return is_string($photo) ? $photo : '';
    }

    /**
     * Memulai: pemilik, atasan, atau OB mana pun untuk tugas kolam yang belum diambil.
     */
    private static function canStart(ObChecksheet $record): bool
    {
        return self::canWorkOn($record) || ($record->isUnclaimed() && $record->isAssigned() && auth()->check());
    }

    /**
     * Menyelesaikan: hanya pemilik tugas, atau atasan yang mewakili.
     */
    private static function canWorkOn(ObChecksheet $record): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->can('updateAll', ObChecksheet::class) || $record->user_id === $user->id);
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            ObChecksheet::STATUS_IN_PROGRESS => 'warning',
            ObChecksheet::STATUS_COMPLETED => 'success',
            default => 'gray',
        };
    }

    private static function timeDescription(ObChecksheet $record): string
    {
        if ($record->isPending()) {
            return 'Belum dikerjakan';
        }

        $started = $record->started_at?->format('d M H:i') ?? '';

        if (! $record->showsDuration()) {
            return $record->finished_at !== null
                ? "{$started} → {$record->finished_at->format('H:i')}"
                : $started;
        }

        if ($record->duration_minutes !== null) {
            return "{$started} ({$record->duration_minutes} mnt)";
        }

        return $record->status === ObChecksheet::STATUS_IN_PROGRESS ? "{$started} • Berjalan..." : $started;
    }

    /**
     * @return array<int, string>
     */
    private static function roomSuggestions(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $query = ObChecksheet::query()
            ->whereNotNull('room')
            ->where('room', '!=', '');

        if (! $user->can('viewAll', ObChecksheet::class)) {
            return $query->where('user_id', $user->id)
                ->distinct()
                ->pluck('room')
                ->all();
        }

        return $query->distinct()->pluck('room')->all();
    }

    private static function photoPreview(string $name, string $label, string $directory): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('public')
            ->directory($directory)
            ->previewable()
            ->openable()
            ->imagePreviewHeight('250')
            ->visibility(fn (): string => StoredFile::uploadVisibility())
            ->maxSize(10240)
            ->afterStateHydrated(function (FileUpload $component, string $operation): void {
                if ($operation !== 'create' || ! is_array($component->getRawState())) {
                    return;
                }

                // FileUpload stores its state as an array. On create that array
                // shares a path with CameraCapture, which expects a string.
                $component->rawState(null);
            });
    }
}
