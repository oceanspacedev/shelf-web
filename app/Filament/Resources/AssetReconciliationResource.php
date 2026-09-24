<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetReconciliationResource\Pages;
use App\Filament\Resources\AssetReconciliationResource\RelationManagers\ItemsRelationManager;
use App\Models\Asset;
use App\Models\AssetReconciliation;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssetReconciliationResource extends Resource
{
    protected static ?string $model = AssetReconciliation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Import & Laporan Audit';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('import', Asset::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('1. Import Workbook Audit')
                ->description('Alur wajib: Export format → isi audit → Import → Laporan gap → Apply. File di-stage dan dibandingkan dulu; data Shelf baru berubah setelah Terapkan Koreksi dikonfirmasi.')
                ->schema([
                    Select::make('source_system')
                        ->label('Jenis Audit')
                        ->options([
                            'CSA' => 'CSA (stok ritel / inventori)',
                            'VEHICLE_AUDIT' => 'Kendaraan (Monitoring Asset / LHP)',
                        ])
                        ->default('CSA')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (callable $set, ?string $state): void {
                            $set('source_sheet', $state === 'VEHICLE_AUDIT' ? 'Monitoring Asset' : 'ASET');
                            // Vehicle create is opt-in; CSA keeps create-location on by default.
                            $set('auto_create_locations', $state !== 'VEHICLE_AUDIT');
                        })
                        ->helperText('CSA memakai sheet ASET (qty). Kendaraan memakai sheet Monitoring Asset (plat).')
                        ->columnSpanFull(),
                    Select::make('business_entity_id')
                        ->label('Badan Usaha Default')
                        ->relationship('businessEntity', 'name')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Pilih badan usaha resmi')
                        ->helperText(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Dipakai bila ACC/STNK tidak punya alias resmi. Alias MSI/CS/TOP/MKLI dipetakan exact ke master.'
                            : 'Dipakai untuk baris tanpa marker badan usaha dari CSA. Marker resmi seperti CSN dan override Gudang memiliki prioritas lebih tinggi.')
                        ->columnSpanFull(),
                    FileUpload::make('stored_path')
                        ->label('File Excel Audit')
                        ->disk(fn (): string => config('filesystems.default'))
                        ->visibility('private')
                        ->directory('asset-reconciliations')
                        ->storeFileNamesIn('original_filename')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(20480)
                        ->required()
                        ->columnSpanFull(),
                    TextInput::make('source_sheet')
                        ->label(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Nama Sheet Monitoring'
                            : 'Nama Sheet Aset')
                        ->default('ASET')
                        ->required()
                        ->maxLength(100),
                    Toggle::make('auto_create_locations')
                        ->label(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Buat aset/lokasi baru dari audit (opt-in)'
                            : 'Buat lokasi baru untuk kode gudang yang belum dikenal')
                        ->helperText(fn (callable $get): string => $get('source_system') === 'VEHICLE_AUDIT'
                            ? 'Off = plat hilang / keberadaan baru → Terblokir. On = saat Apply boleh buat aset MOBIL/MOTOR dan lokasi dari CEK KEBERADAAN. Tidak mengubah Shelf saat preview.'
                            : 'Lokasi baru hanya dibuat ketika koreksi diterapkan, tidak saat preview compare.')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('original_filename')->label('Workbook')->searchable()->limit(36),
                TextColumn::make('source_system')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'VEHICLE_AUDIT' => 'Kendaraan',
                        'CSA' => 'CSA',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => $state === 'VEHICLE_AUDIT' ? 'info' : 'gray'),
                TextColumn::make('businessEntity.name')->label('Badan Usaha Default')->searchable()->sortable()->toggleable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                    ->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('total_rows')->label('Baris')->numeric(),
                TextColumn::make('inline_rows')->label('Inline')->numeric()->color('success'),
                TextColumn::make('gap_rows')->label('Gap')->numeric()->color('warning'),
                TextColumn::make('blocked_rows')->label('Blokir')->numeric()->color('danger'),
                TextColumn::make('importer.name')->label('Pengguna')->toggleable(),
                TextColumn::make('applied_at')->label('Diterapkan')->dateTime('d M Y H:i')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('source_system')->label('Jenis Audit')->options([
                    'CSA' => 'CSA',
                    'VEHICLE_AUDIT' => 'Kendaraan',
                ]),
                SelectFilter::make('status')->options([
                    AssetReconciliation::STATUS_PROCESSING => self::statusLabel(AssetReconciliation::STATUS_PROCESSING),
                    AssetReconciliation::STATUS_COMPARED => self::statusLabel(AssetReconciliation::STATUS_COMPARED),
                    AssetReconciliation::STATUS_ALIGNED => self::statusLabel(AssetReconciliation::STATUS_ALIGNED),
                    AssetReconciliation::STATUS_APPLIED => self::statusLabel(AssetReconciliation::STATUS_APPLIED),
                    AssetReconciliation::STATUS_FAILED => self::statusLabel(AssetReconciliation::STATUS_FAILED),
                ]),
            ])
            ->actions([
                ViewAction::make()->label('Buka Laporan'),
            ])
            ->emptyStateHeading('Belum ada batch audit')
            ->emptyStateDescription('Pilih CSA atau Audit Kendaraan: Export format → isi audit → Import → Laporan → Apply.')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('1. Import Audit')
                    ->icon('heroicon-o-document-magnifying-glass'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('2. Laporan Hasil Banding')
                ->description('Tinjau Inline, Gap, dan Blocked sebelum Apply. Tidak ada mutasi Shelf pada tahap ini.')
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => self::statusLabel($state))
                            ->color(fn (string $state): string => self::statusColor($state)),
                        TextEntry::make('source_system')->label('Sumber'),
                        TextEntry::make('source_sheet')->label('Sheet'),
                        TextEntry::make('original_filename')->label('Workbook'),
                    ]),
                    TextEntry::make('businessEntity.name')
                        ->label('Badan Usaha Default')
                        ->placeholder('Batch lama: belum ditentukan')
                        ->color(fn (?string $state): string => filled($state) ? 'primary' : 'danger'),
                    Grid::make(4)->schema([
                        TextEntry::make('total_rows')->label('Total Baris')->numeric(),
                        TextEntry::make('inline_rows')->label('Sudah Inline')->numeric()->color('success'),
                        TextEntry::make('gap_rows')->label('Perlu Koreksi')->numeric()->color('warning'),
                        TextEntry::make('blocked_rows')->label('Terblokir')->numeric()->color('danger'),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('created_rows')->label('Aset Dibuat')->numeric(),
                        TextEntry::make('updated_rows')->label('Aset Disesuaikan')->numeric(),
                        TextEntry::make('retired_rows')
                            ->label(fn (?AssetReconciliation $record): string => ($record?->source_system ?? null) === 'VEHICLE_AUDIT'
                                ? 'Dinonaktifkan / Terjual'
                                : 'Saldo Jadi 0')
                            ->numeric(),
                    ]),
                    TextEntry::make('summary.actions')
                        ->label('Ringkasan Aksi Preview')
                        ->formatStateUsing(function (mixed $state): string {
                            if (! is_array($state) || $state === []) {
                                return '-';
                            }

                            $labels = [
                                'mark_sold' => 'Tandai terjual',
                                'retire_duplicate' => 'Nonaktifkan duplikat',
                                'create_missing' => 'Buat aset',
                                'enrich' => 'Lengkapi identitas',
                                'none' => 'Tanpa aksi',
                                'create' => 'Buat aset',
                                'adjust' => 'Sesuaikan',
                                'retire' => 'Saldo 0',
                                'review' => 'Tinjau',
                            ];

                            return collect($state)
                                ->map(fn (int $count, string $action): string => ($labels[$action] ?? $action).": {$count}")
                                ->implode(' · ');
                        })
                        ->columnSpanFull()
                        ->visible(fn (?AssetReconciliation $record): bool => filled($record?->summary['actions'] ?? null)),
                    TextEntry::make('summary.orphan_shelf_plates')
                        ->label('Plat Shelf tanpa pasangan audit')
                        ->numeric()
                        ->helperText('Observasi saja — tidak dihapus otomatis (FR-VA-013).')
                        ->visible(fn (?AssetReconciliation $record): bool => ($record?->source_system ?? null) === 'VEHICLE_AUDIT'
                            && array_key_exists('orphan_shelf_plates', $record?->summary ?? [])),
                    TextEntry::make('parent.uuid')
                        ->label('Batch Induk')
                        ->placeholder('-')
                        ->visible(fn (?AssetReconciliation $record): bool => filled($record?->parent_id)),
                    TextEntry::make('compared_at')
                        ->label('Waktu Laporan')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('applied_at')
                        ->label('Waktu Apply')
                        ->dateTime('d M Y H:i')
                        ->placeholder('-'),
                    TextEntry::make('failure_message')
                        ->label('Penyebab Gagal')
                        ->color('danger')
                        ->columnSpanFull()
                        ->visible(fn (?AssetReconciliation $record): bool => filled($record?->failure_message)),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [ItemsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetReconciliations::route('/'),
            'create' => Pages\CreateAssetReconciliation::route('/create'),
            'view' => Pages\ViewAssetReconciliation::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Import & Laporan Audit Aset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import & Laporan Audit Aset';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            AssetReconciliation::STATUS_PROCESSING => 'Memproses',
            AssetReconciliation::STATUS_COMPARED => 'Preview tersedia',
            AssetReconciliation::STATUS_ALIGNED => 'Sudah inline',
            AssetReconciliation::STATUS_APPLIED => 'Koreksi diterapkan',
            AssetReconciliation::STATUS_FAILED => 'Gagal',
            default => $status,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            AssetReconciliation::STATUS_ALIGNED => 'success',
            AssetReconciliation::STATUS_APPLIED => 'primary',
            AssetReconciliation::STATUS_COMPARED => 'warning',
            AssetReconciliation::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}
