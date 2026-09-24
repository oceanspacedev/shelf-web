<?php

namespace App\Filament\Resources;

use App\Enums\AssetServiceStatus;
use App\Filament\Exports\AssetServiceExporter;
use App\Filament\Resources\AssetServiceResource\Pages;
use App\Models\Asset;
use App\Models\AssetService;
use App\Models\Vendor;
use App\Support\StoredFile;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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

class AssetServiceResource extends Resource
{
    protected static ?string $model = AssetService::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Servis Aset');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Servis Aset');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Hidden::make('created_by')
                    ->default(fn () => auth()->id()),

                Section::make('Informasi Aset & Waktu Servis')
                    ->schema([
                        TextInput::make('service_number')
                            ->label('Nomor Servis')
                            ->default(fn () => AssetService::generateServiceNumber())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Select::make('asset_id')
                            ->label('Aset yang Diservis')
                            ->relationship('asset', 'name')
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search): array {
                                return Asset::query()
                                    ->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('serial_number', 'like', "%{$search}%")
                                            ->orWhere('type', 'like', "%{$search}%");
                                    })
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(function (Asset $asset) {
                                        $detail = array_filter([$asset->serial_number, $asset->businessEntity?->name]);
                                        $suffix = ! empty($detail) ? ' (' . implode(' - ', $detail) . ')' : '';

                                        return [$asset->id => $asset->name . $suffix];
                                    })
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $asset = Asset::with('businessEntity')->find($value);
                                if (! $asset) {
                                    return null;
                                }
                                $detail = array_filter([$asset->serial_number, $asset->businessEntity?->name]);
                                $suffix = ! empty($detail) ? ' (' . implode(' - ', $detail) . ')' : '';

                                return $asset->name . $suffix;
                            })
                            ->required(),

                        DatePicker::make('service_date')
                            ->label('Tanggal Servis')
                            ->default(now())
                            ->required(),

                        DatePicker::make('completion_date')
                            ->label('Tanggal Selesai')
                            ->visible(fn (string $operation) => $operation !== 'create'),

                        Select::make('status')
                            ->label('Status')
                            ->options(AssetServiceStatus::options())
                            ->default(AssetServiceStatus::Pending->value)
                            ->required()
                            ->visible(fn (string $operation) => $operation !== 'create'),
                    ])
                    ->columns(['default' => 1, 'md' => 2, 'lg' => 3]),

                Section::make('Pelaksana Servis')
                    ->schema([
                        Select::make('provider_type')
                            ->label('Tipe Pelaksana')
                            ->options([
                                'internal' => 'Internal',
                                'vendor' => 'Vendor Eksternal',
                            ])
                            ->default('internal')
                            ->required()
                            ->live(),

                        Select::make('serviced_by_user_id')
                            ->label('Teknisi Internal')
                            ->relationship('servicedByUser', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('provider_type') === 'internal')
                            ->required(fn (callable $get) => $get('provider_type') === 'internal'),

                        Select::make('vendor_id')
                            ->label('Vendor Terdaftar')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => $get('provider_type') === 'vendor'),

                        TextInput::make('technician_name')
                            ->label('Nama Teknisi / Bengkel')
                            ->visible(fn (callable $get) => $get('provider_type') === 'vendor'),

                        TextInput::make('contact_number')
                            ->label('No. Telepon / WA')
                            ->tel()
                            ->visible(fn (callable $get) => $get('provider_type') === 'vendor'),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Section::make('Kendala & Foto Kerusakan')
                    ->schema([
                        Textarea::make('issue_description')
                            ->label('Kendala / Kerusakan')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('before_service_photo')
                            ->label('Foto Sebelum Servis (Kerusakan)')
                            ->image()
                            ->directory('asset-services/before')
                            ->maxSize(10240)
                            ->imageEditor()
                            ->extraInputAttributes(['capture' => 'environment'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Rincian Suku Cadang & Jasa')
                    ->visible(fn (string $operation) => $operation !== 'create')
                    ->schema([
                        Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->schema([
                                TextInput::make('item_name')
                                    ->label('Nama Item / Jasa')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 5]),

                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                TextInput::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                TextInput::make('notes')
                                    ->label('Catatan / Garansi')
                                    ->columnSpan(['default' => 12, 'md' => 2]),
                            ])
                            ->columns(12)
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $qty = (int) ($item['quantity'] ?? 1);
                                    $price = (int) ($item['unit_price'] ?? 0);
                                    $total += ($qty * $price);
                                }
                                if ($total > 0) {
                                    $set('total_cost', $total);
                                }
                            })
                            ->defaultItems(0)
                            ->addActionLabel('+ Tambah Item'),
                    ]),

                Section::make('Hasil Perbaikan & Dokumen')
                    ->visible(fn (string $operation) => $operation !== 'create')
                    ->schema([
                        Textarea::make('action_taken')
                            ->label('Tindakan Perbaikan')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('after_service_photo')
                            ->label('Foto Sesudah')
                            ->image()
                            ->directory('asset-services/after')
                            ->maxSize(10240)
                            ->imageEditor()
                            ->extraInputAttributes(['capture' => 'environment']),

                        FileUpload::make('receipt_document_path')
                            ->label('Nota / Kuitansi')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->directory('asset-services/receipts')
                            ->maxSize(10240)
                            ->openable()
                            ->downloadable(),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),

                Section::make('Biaya & Catatan')
                    ->visible(fn (string $operation) => $operation !== 'create')
                    ->schema([
                        TextInput::make('total_cost')
                            ->label('Total Biaya')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0),

                        Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(2),
                    ])
                    ->columns(['default' => 1, 'md' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_number')
                    ->label('No. Servis')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('asset.name')
                    ->label('Aset')
                    ->description(fn (AssetService $record): string => $record->asset?->serial_number ? "SN: {$record->asset->serial_number}" : '')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provider_label')
                    ->label('Pelaksana')
                    ->badge()
                    ->color(fn (AssetService $record): string => $record->provider_type === 'internal' ? 'gray' : 'info'),

                TextColumn::make('service_date')
                    ->label('Tgl Servis')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('completion_date')
                    ->label('Tgl Selesai')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('Biaya')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => $state instanceof AssetServiceStatus ? $state->color() : 'gray')
                    ->formatStateUsing(fn ($state) => $state instanceof AssetServiceStatus ? $state->label() : (string) $state),

                ImageColumn::make('before_service_photo')
                    ->label('Foto Sebelum')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: false),

                ImageColumn::make('after_service_photo')
                    ->label('Foto Sesudah')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('creator.name')
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Servis')
                    ->options(AssetServiceStatus::options()),

                SelectFilter::make('provider_type')
                    ->label('Tipe Pelaksana')
                    ->options([
                        'internal' => 'Internal',
                        'vendor' => 'Eksternal (Vendor)',
                    ]),
            ])
            ->actions([
                Action::make('completeService')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->modalWidth('3xl')
                    ->modalHeading(fn (AssetService $record) => 'Selesaikan Servis: ' . ($record->asset?->name ?? $record->service_number))
                    ->modalSubmitActionLabel('Simpan & Selesaikan')
                    ->visible(function (AssetService $record) {
                        $user = auth()->user();
                        if (! $user) {
                            return false;
                        }
                        if ($record->status === AssetServiceStatus::Completed || $record->status === AssetServiceStatus::Cancelled) {
                            return false;
                        }

                        return $user->hasRole(['super_admin', 'admin', 'general_affair'])
                            || $record->created_by === $user->id
                            || $record->serviced_by_user_id === $user->id;
                    })
                    ->mountUsing(fn ($form, AssetService $record) => $form->fill([
                        'completion_date' => now()->toDateString(),
                        'action_taken' => $record->action_taken,
                        'total_cost' => $record->total_cost ?: 0,
                        'notes' => $record->notes,
                        'items' => $record->items->toArray(),
                    ]))
                    ->form([
                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                DatePicker::make('completion_date')
                                    ->label('Tanggal Selesai')
                                    ->default(now())
                                    ->required(),

                                TextInput::make('total_cost')
                                    ->label('Total Biaya (Rp)')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0),
                            ]),

                        Textarea::make('action_taken')
                            ->label('Tindakan Perbaikan')
                            ->required()
                            ->rows(2)
                            ->columnSpanFull(),

                        Repeater::make('items')
                            ->label('Rincian Suku Cadang & Jasa (Opsional)')
                            ->schema([
                                TextInput::make('item_name')
                                    ->label('Nama Item / Jasa')
                                    ->required()
                                    ->columnSpan(['default' => 12, 'md' => 5]),

                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 6, 'md' => 2]),

                                TextInput::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->prefix('Rp')
                                    ->default(0)
                                    ->required()
                                    ->live()
                                    ->columnSpan(['default' => 6, 'md' => 3]),

                                TextInput::make('notes')
                                    ->label('Garansi / Ket.')
                                    ->columnSpan(['default' => 12, 'md' => 2]),
                            ])
                            ->columns(12)
                            ->live()
                            ->afterStateUpdated(function (callable $get, callable $set) {
                                $items = $get('items') ?? [];
                                $total = 0;
                                foreach ($items as $item) {
                                    $qty = (int) ($item['quantity'] ?? 1);
                                    $price = (int) ($item['unit_price'] ?? 0);
                                    $total += ($qty * $price);
                                }
                                if ($total > 0) {
                                    $set('total_cost', $total);
                                }
                            })
                            ->addActionLabel('+ Tambah Item')
                            ->columnSpanFull(),

                        Grid::make(['default' => 1, 'md' => 2])
                            ->schema([
                                FileUpload::make('after_service_photo')
                                    ->label('Foto Hasil Perbaikan (Sesudah)')
                                    ->image()
                                    ->directory('asset-services/after')
                                    ->maxSize(10240)
                                    ->imageEditor()
                                    ->extraInputAttributes(['capture' => 'environment']),

                                FileUpload::make('receipt_document_path')
                                    ->label('Foto Nota / Kuitansi')
                                    ->acceptedFileTypes(['image/*', 'application/pdf'])
                                    ->directory('asset-services/receipts')
                                    ->maxSize(10240)
                                    ->openable()
                                    ->downloadable(),
                            ]),

                        Textarea::make('notes')
                            ->label('Catatan Tambahan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->action(function (AssetService $record, array $data) {
                        $record->update([
                            'completion_date' => $data['completion_date'] ?? now(),
                            'action_taken' => $data['action_taken'] ?? null,
                            'total_cost' => $data['total_cost'] ?? 0,
                            'after_service_photo' => $data['after_service_photo'] ?? null,
                            'receipt_document_path' => $data['receipt_document_path'] ?? null,
                            'notes' => $data['notes'] ?? null,
                            'status' => AssetServiceStatus::Completed,
                        ]);

                        if (isset($data['items']) && is_array($data['items'])) {
                            $record->items()->delete();
                            foreach ($data['items'] as $item) {
                                if (! empty($item['item_name'])) {
                                    $record->items()->create([
                                        'item_name' => $item['item_name'],
                                        'quantity' => $item['quantity'] ?? 1,
                                        'unit_price' => $item['unit_price'] ?? 0,
                                        'notes' => $item['notes'] ?? null,
                                    ]);
                                }
                            }
                            $record->recalculateTotalCost();
                        }

                        Notification::make()
                            ->title('Servis Selesai!')
                            ->body("Servis untuk aset {$record->asset?->name} ({$record->service_number}) telah diselesaikan.")
                            ->success()
                            ->send();
                    }),

                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(AssetServiceExporter::class)
                        ->label('Export Dipilih'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('Detail Servis Aset')
                    ->schema([
                        TextEntry::make('service_number')->label('No. Servis')->weight('bold'),
                        TextEntry::make('asset.name')->label('Nama Aset'),
                        TextEntry::make('asset.serial_number')->label('No. Seri / IMEI')->placeholder('-'),
                        TextEntry::make('status')->label('Status')->badge()->color(fn ($state) => $state instanceof AssetServiceStatus ? $state->color() : 'gray')->formatStateUsing(fn ($state) => $state instanceof AssetServiceStatus ? $state->label() : (string) $state),
                        TextEntry::make('provider_label')->label('Pelaksana Servis'),
                        TextEntry::make('contact_number')->label('Kontak')->placeholder('-'),
                        TextEntry::make('service_date')->label('Tanggal Masuk Servis')->date('d/m/Y'),
                        TextEntry::make('completion_date')->label('Tanggal Selesai')->date('d/m/Y')->placeholder('Belum Selesai'),
                        TextEntry::make('total_cost')->label('Total Biaya')->money('IDR', locale: 'id'),
                        TextEntry::make('creator.name')->label('Dibuat Oleh')->placeholder('-'),
                        TextEntry::make('issue_description')->label('Keluhan / Kerusakan')->columnSpanFull(),
                        TextEntry::make('action_taken')->label('Tindakan Perbaikan')->placeholder('-')->columnSpanFull(),
                        TextEntry::make('notes')->label('Catatan')->placeholder('-')->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),

                Section::make('Rincian Suku Cadang & Jasa')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('item_name')->label('Item / Pekerjaan'),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('unit_price')->label('Harga Satuan')->money('IDR', locale: 'id'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('IDR', locale: 'id'),
                                TextEntry::make('notes')->label('Catatan / Garansi')->placeholder('-'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Dokumentasi Foto & Nota')
                    ->schema([
                        ImageEntry::make('before_service_photo')->label('Foto Sebelum Servis')->height(200),
                        ImageEntry::make('after_service_photo')->label('Foto Sesudah Servis')->height(200),
                        TextEntry::make('receipt_document_path')
                            ->label('Nota / Bukti Kuitansi')
                            ->url(fn (?AssetService $record) => $record?->receipt_document_path ? StoredFile::url(ltrim($record->receipt_document_path, '/')) : null, true)
                            ->openUrlInNewTab()
                            ->visible(fn (?AssetService $record) => filled($record?->receipt_document_path)),
                    ])
                    ->columns(['default' => 1, 'md' => 3]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query;
        }

        // Pengawas / Admin dapat melihat seluruh servis aset
        if ($user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            return $query;
        }

        // User biasa hanya melihat data servis yang dibuat olehnya atau ditugaskan kepadanya
        return $query->where(function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhere('serviced_by_user_id', $user->id);
        });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetServices::route('/'),
            'create' => Pages\CreateAssetService::route('/create'),
            'view' => Pages\ViewAssetService::route('/{record}'),
            'edit' => Pages\EditAssetService::route('/{record}/edit'),
        ];
    }
}
