<?php

namespace App\Filament\Resources;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource\Pages;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\StoredFile;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AssetTransferResource extends Resource
{
    protected static ?string $model = AssetTransfer::class;

    /**
     * Opsi select pemberi/penerima/aset yang dikirim ke browser per render.
     * Browser juga hanya menggambar 50 opsi; sisanya lewat pencarian server.
     */
    protected const OPTIONS_LIMIT = 50;

    public static function getModelLabel(): string
    {
        return __('Asset Transfer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asset Transfers');
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function form(Schema $form): Schema
    {
        $viewer = Auth::user();
        $mayUpdateCore = $viewer instanceof User && $viewer->can('updateCore', AssetTransfer::class);
        $viewerIsGeneralAffair = $viewer instanceof User && $viewer->isGeneralAffair();
        $viewerId = $viewerIsGeneralAffair ? $viewer->getKey() : null;
        $lockedOnEdit = fn (string $operation): bool => $operation === 'edit' && ! $mayUpdateCore;
        // BA yang menyentuh stok (Serah Terima, Pengembalian) hanya untuk staf GA
        // dan pemegang izin "Kelola BA Stok". Pihak GA pada BA staf GA adalah
        // akunnya sendiri; hanya pemegang izin itu yang boleh memilih staf GA lain. Dipaksa lagi di server oleh
        // CreateAssetTransfer::mutateFormDataBeforeCreate dan AssetTransfer.
        $mayHandleStock = $viewer instanceof User && $viewer->canCreateStockTransfers();
        $generalAffairSideLocked = $viewerIsGeneralAffair && ! $viewer->canManageStockTransfers();

        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Section::make('Informasi Transfer')
                            ->schema([
                                Select::make('document_type')
                                    ->label('Jenis Berita Acara')
                                    ->options(fn (string $operation): array => static::documentTypeOptions($operation !== 'create' || $mayHandleStock))
                                    ->required()
                                    ->live()
                                    ->native(false)
                                    ->default(match (true) {
                                        $viewerIsGeneralAffair => AssetTransferDocumentType::SerahTerima->value,
                                        ! $mayHandleStock => AssetTransferDocumentType::PengalihanBarang->value,
                                        default => null,
                                    })
                                    ->disabled($lockedOnEdit)
                                    ->helperText(fn (Get $get): ?string => static::documentType($get)?->description())
                                    ->afterStateUpdated(function ($state, Set $set) use ($viewerId): void {
                                        $type = AssetTransferDocumentType::tryFrom((string) $state);

                                        // Staf GA yang login otomatis menjadi pihak GA pada BA-nya.
                                        $set('from_user_id', $type?->dispatchesFromStock() ? $viewerId : null);
                                        $set('to_user_id', $type?->returnsToStock() ? $viewerId : null);
                                        $set('details', static::defaultDetails($type, $type?->dispatchesFromStock() ? $viewerId : null));
                                    }),
                                TextInput::make('letter_number')
                                    ->translateLabel()
                                    ->disabled($lockedOnEdit)
                                    ->extraInputAttributes(['readonly' => true]),
                                Select::make('business_entity_id')
                                    ->translateLabel()
                                    ->options(fn (?AssetTransfer $record): array => BusinessEntity::optionsFor($viewer, $record?->business_entity_id))
                                    ->in(fn (?AssetTransfer $record): array => array_keys(BusinessEntity::optionsFor($viewer, $record?->business_entity_id)))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disabled($lockedOnEdit)
                                    ->afterStateUpdated(fn ($state, Set $set) => $set(
                                        'letter_number',
                                        AssetTransfer::generateLetterNumber(BusinessEntity::find($state), null)
                                    )),
                                Select::make('from_user_id')
                                    ->label(fn (Get $get): string => static::documentType($get)?->dispatchesFromStock()
                                        ? 'Staf GA yang Menyerahkan'
                                        : 'Dari Pemegang')
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->disabled(fn (string $operation, Get $get): bool => $lockedOnEdit($operation)
                                        || ($generalAffairSideLocked && (bool) static::documentType($get)?->requiresGeneralAffairFrom()))
                                    ->default($viewerId)
                                    ->options(fn (Get $get): array => static::partyOptions(static::documentType($get), 'from', $get('to_user_id')))
                                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::partyOptions(static::documentType($get), 'from', $get('to_user_id'), $search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::userLabel($value))
                                    ->helperText(fn (Get $get): ?string => match (true) {
                                        ! static::documentType($get)?->dispatchesFromStock() => null,
                                        $generalAffairSideLocked => 'Terkunci ke akun Anda yang sedang login. Hanya pemegang izin Kelola BA Stok yang bisa memilih staf GA lain.',
                                        default => 'Hanya staf dengan role general_affair yang bisa mengeluarkan aset dari stok.',
                                    })
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        if ($state && (int) $state === (int) $get('to_user_id')) {
                                            $set('to_user_id', null);
                                        }

                                        $set('details', static::defaultDetails(static::documentType($get), $state));
                                    }),
                                Select::make('to_user_id')
                                    ->label(fn (Get $get): string => static::documentType($get)?->returnsToStock()
                                        ? 'Staf GA yang Menerima'
                                        : 'Ke Penerima')
                                    ->required()
                                    ->live()
                                    ->searchable()
                                    ->disabled(fn (string $operation, Get $get): bool => $lockedOnEdit($operation)
                                        || ($generalAffairSideLocked && (bool) static::documentType($get)?->requiresGeneralAffairTo()))
                                    ->options(fn (Get $get): array => static::partyOptions(static::documentType($get), 'to', $get('from_user_id')))
                                    ->getSearchResultsUsing(fn (string $search, Get $get): array => static::partyOptions(static::documentType($get), 'to', $get('from_user_id'), $search))
                                    ->getOptionLabelUsing(fn ($value): ?string => static::userLabel($value))
                                    ->helperText(fn (Get $get): ?string => match (true) {
                                        ! static::documentType($get)?->returnsToStock() => null,
                                        $generalAffairSideLocked => 'Terkunci ke akun Anda yang sedang login. Hanya pemegang izin Kelola BA Stok yang bisa memilih staf GA lain.',
                                        default => 'Hanya staf dengan role general_affair yang bisa menerima aset kembali ke stok.',
                                    })
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->translateLabel()
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('business_entity_id')
                                            ->options(fn (): array => BusinessEntity::optionsFor($viewer))
                                            ->in(fn (): array => array_keys(BusinessEntity::optionsFor($viewer)))
                                            ->translateLabel()
                                            ->searchable(),
                                        Select::make('job_title_id')
                                            ->options(fn () => Cache::remember('job_title_options', 300, fn () => JobTitle::orderBy('title')->pluck('title', 'id')->toArray()))
                                            ->translateLabel()
                                            ->searchable(),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $user = User::create([
                                            'name' => $data['name'],
                                            'business_entity_id' => $data['business_entity_id'],
                                            'job_title_id' => $data['job_title_id'],
                                        ]);
                                        Cache::forget('user_options');

                                        return $user->id;
                                    }),
                                DatePicker::make('transfer_date')
                                    ->native(false)
                                    ->disabled($lockedOnEdit)
                                    ->required(),
                            ])
                            ->columnSpan(1),
                        FileUpload::make('document')
                            ->preserveFilenames()
                            ->directory('document')
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => (string) Str::of($file->getClientOriginalName())
                                    ->prepend(mt_rand(100, 999).'-')
                            )
                            ->columnSpan(1)
                            ->hidden(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->columns(1)
                    ->columnSpan(1),
                Repeater::make('details')
                    ->relationship('details')
                    ->disabled($lockedOnEdit)
                    ->schema([
                        Select::make('asset_id')
                            ->live()
                            ->required()
                            ->translateLabel()
                            ->searchable()
                            ->disabled($lockedOnEdit)
                            ->options(fn (Get $get): array => static::assetOptions(
                                static::documentType($get, '../../document_type'),
                                $get('../../from_user_id'),
                                collect($get('../../details'))->pluck('asset_id')->filter()->all(),
                            ))
                            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::assetOptions(
                                static::documentType($get, '../../document_type'),
                                $get('../../from_user_id'),
                                collect($get('../../details'))->pluck('asset_id')->filter()->all(),
                                $search,
                            ))
                            ->getOptionLabelUsing(fn ($value): ?string => Asset::find($value)?->name),
                        TextInput::make('equipment')
                            ->translateLabel()
                            ->disabled($lockedOnEdit),
                    ])
                    ->translateLabel()
                    ->required()
                    ->hidden(fn (Get $get): bool => ! static::documentType($get) || ! $get('from_user_id'))
                    ->columns(2)
                    ->columnSpan(2),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('businessEntity.name')
                    ->translateLabel()
                    ->badge()
                    ->color(fn ($record) => $record->businessEntity?->color)
                    ->getStateUsing(fn ($record) => $record->businessEntity?->name)
                    ->toggleable(),
                TextColumn::make('document_type')
                    ->label('Jenis BA')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof AssetTransferDocumentType ? $state->label() : 'Status Transfer Tidak Valid')
                    ->color(fn ($state): string => $state instanceof AssetTransferDocumentType ? $state->color() : 'gray')
                    ->placeholder('Status Transfer Tidak Valid')
                    ->toggleable(),
                TextColumn::make('letter_number')
                    ->translateLabel()
                    ->badge()
                    ->toggleable(),
                TextColumn::make('fromUser.name')
                    ->translateLabel()
                    ->badge()
                    ->color('danger')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('toUser.name')
                    ->translateLabel()
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('transfer_date')->translateLabel()->date()->toggleable(),
                TextColumn::make('document')
                    ->url(fn ($record) => $record && $record->document ? StoredFile::url($record->document) : null, true)
                    ->openUrlInNewTab()
                    ->translateLabel()
                    ->getStateUsing(fn ($record) => $record && $record->document ? 'Dokumen' : '-')
                    ->icon('heroicon-o-document-text')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('businessEntity')->relationship('businessEntity', 'name')->translateLabel(),
                SelectFilter::make('document_type')
                    ->label('Jenis BA')
                    ->options(AssetTransferDocumentType::options()),
                SelectFilter::make('fromUser')
                    ->relationship('fromUser', 'name')
                    ->label('Dari Pengguna')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('toUser')
                    ->relationship('toUser', 'name')
                    ->label('Ke Pengguna')
                    ->searchable()
                    ->preload(),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->actions([
                Action::make('download')
                    ->label('Template')
                    ->url(fn (AssetTransfer $record): string => route('asset-transfer.download', $record))
                    ->visible(fn (AssetTransfer $record): bool => $record->document === null)
                    ->color('success'),
                Action::make('clear_document')
                    ->label('Kosongkan Dokumen')
                    ->icon('heroicon-o-trash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Kosongkan Dokumen')
                    ->modalDescription('Apakah Anda yakin ingin mengosongkan dokumen ini?')
                    ->modalSubmitActionLabel('Ya, kosongkan')
                    ->visible(fn (AssetTransfer $record): bool => $record->document !== null)
                    ->action(function (AssetTransfer $record) {
                        if ($record->document) {
                            Storage::disk('public')->delete($record->document);
                            $record->update(['document' => null]);
                            Notification::make()
                                ->title('Dokumen berhasil dikosongkan')
                                ->success()
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Pengguna dengan akses badan usaha terbatas hanya melihat BA dari badan
     * usaha yang bisa diaksesnya.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $viewer = auth()->user();

        return $viewer instanceof User ? $query->accessibleBy($viewer) : $query;
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
            'index' => Pages\ListAssetTransfers::route('/'),
            'create' => Pages\CreateAssetTransfer::route('/create'),
            'edit' => Pages\EditAssetTransfer::route('/{record}/edit'),
            'view' => Pages\ViewAssetTransfer::route('/{record}'),
        ];
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                Section::make('📄 Informasi Transfer Aset')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('letter_number')
                                    ->label('Nomor Surat')
                                    ->extraAttributes([
                                        'style' => 'font-weight: bold; color: #1a202c;',
                                    ]),
                                TextEntry::make('status')
                                    ->label('Jenis Berita Acara')
                                    ->badge()
                                    ->colors(AssetTransferDocumentType::colors()),
                                TextEntry::make('fromUser.name')
                                    ->label('Dari Pengguna')
                                    ->icon('heroicon-o-user')
                                    ->columnSpan(1),
                                TextEntry::make('toUser.name')
                                    ->label('Ke Pengguna')
                                    ->icon('heroicon-o-user')
                                    ->columnSpan(1),
                                TextEntry::make('transfer_date')
                                    ->label('Tanggal Transfer')
                                    ->date()
                                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d M Y'))
                                    ->extraAttributes(['style' => 'font-weight: bold;']),
                                TextEntry::make('businessEntity.name')
                                    ->label('Entitas Bisnis')
                                    ->icon('heroicon-o-briefcase'),
                                TextEntry::make('document')
                                    ->label('Dokumen')
                                    ->url(fn ($record) => $record->document ? StoredFile::url($record->document) : null, true)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-o-document')
                                    ->getStateUsing(fn ($record) => $record && $record->document ? 'Unduh Dokumen' : 'Tidak Ada Dokumen')
                                    ->extraAttributes(['style' => 'font-weight:bold;color:#007bff;']),
                            ]),
                    ])
                    ->columns(2)
                    ->collapsible(),
                Section::make('📦 Detail Aset yang Ditransfer')
                    ->schema([
                        RepeatableEntry::make('details')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('asset.name')
                                            ->label('Nama Aset')
                                            ->extraAttributes(['style' => 'font-weight: bold;']),
                                        TextEntry::make('equipment')
                                            ->label('Keterangan Peralatan'),
                                    ]),
                            ])
                            ->columnSpan(2),
                    ])
                    ->collapsible()
                    ->columns(2),
            ]);
    }

    /**
     * Staf GA dan pemegang izin "Kelola BA Stok" boleh membuat BA
     * yang menyentuh stok (Serah Terima, Pengembalian); yang lain hanya
     * Pengalihan antar pemegang.
     */
    public static function viewerMayHandleStock(): bool
    {
        $viewer = Auth::user();

        return $viewer instanceof User && $viewer->canCreateStockTransfers();
    }

    /**
     * @return array<string, string>
     */
    protected static function documentTypeOptions(bool $mayHandleStock): array
    {
        $options = AssetTransferDocumentType::options();

        if ($mayHandleStock) {
            return $options;
        }

        $pengalihan = AssetTransferDocumentType::PengalihanBarang->value;

        return [$pengalihan => $options[$pengalihan]];
    }

    protected static function documentType(Get $get, string $path = 'document_type'): ?AssetTransferDocumentType
    {
        $state = $get($path);

        if ($state instanceof AssetTransferDocumentType) {
            return $state;
        }

        return AssetTransferDocumentType::tryFrom((string) $state);
    }

    /**
     * Calon pemberi atau penerima untuk jenis BA yang dipilih. Sisi yang
     * mewakili stok dibatasi ke staf general_affair. Opsi ini dievaluasi ulang
     * setiap render form, jadi dibatasi; user lain dicari lewat pencarian.
     *
     * @return array<int, string>
     */
    protected static function partyOptions(?AssetTransferDocumentType $type, string $side, mixed $excludeUserId = null, ?string $search = null): array
    {
        if (! $type) {
            return [];
        }

        $requiresGeneralAffair = $side === 'from'
            ? $type->requiresGeneralAffairFrom()
            : $type->requiresGeneralAffairTo();

        return User::query()
            ->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', 'super_admin'))
            ->when($requiresGeneralAffair, fn (Builder $query) => $query->generalAffair())
            ->when(filled($excludeUserId), fn (Builder $query) => $query->whereKeyNot($excludeUserId))
            ->when(filled($search), fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
            ->with('jobTitle')
            ->orderBy('name')
            ->limit(static::OPTIONS_LIMIT)
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => static::formatUserLabel($user)])
            ->all();
    }

    protected static function userLabel(mixed $userId): ?string
    {
        $user = User::with('jobTitle')->find($userId);

        return $user ? static::formatUserLabel($user) : null;
    }

    protected static function formatUserLabel(User $user): string
    {
        return $user->name.' - '.($user->jobTitle?->title ?? 'N/A');
    }

    /**
     * Baris detail awal: BA Serah Terima mulai dari satu baris kosong (aset
     * dipilih dari stok), BA lain memuat semua aset yang dipegang pemberi.
     *
     * @return list<array{asset_id: int|null, equipment: null}>
     */
    protected static function defaultDetails(?AssetTransferDocumentType $type, mixed $fromUserId): array
    {
        if (! $type) {
            return [];
        }

        if ($type->dispatchesFromStock()) {
            return [['asset_id' => null, 'equipment' => null]];
        }

        if (! $fromUserId) {
            return [];
        }

        return static::assetQuery($type, $fromUserId)
            ->orderBy('name')
            ->get()
            ->map(fn (Asset $asset): array => ['asset_id' => $asset->id, 'equipment' => null])
            ->all();
    }

    /**
     * Aset yang boleh masuk BA: stok (Tersedia, tanpa pemegang) untuk Serah
     * Terima, aset yang dipegang pemberi untuk BA lainnya; selalu dibatasi ke
     * aset yang bisa diakses pengguna yang login.
     */
    protected static function assetQuery(AssetTransferDocumentType $type, mixed $fromUserId): Builder
    {
        $viewer = Auth::user();
        $query = Asset::query()
            ->notLockedForOpenRequest()
            ->when($viewer instanceof User, fn (Builder $query): Builder => $query->accessibleBy($viewer));

        if ($type->dispatchesFromStock()) {
            return $query
                ->where('condition_status', AssetCondition::Available->value)
                ->whereNull('recipient_id');
        }

        return $query
            ->where('recipient_id', $fromUserId)
            ->whereIn('condition_status', AssetCondition::transferableValues());
    }

    /**
     * Opsi aset per baris detail, dievaluasi ulang setiap render form. Dibatasi
     * supaya stok yang besar tidak dikirim utuh ke browser; aset lain dicari
     * lewat nama atau serial number.
     *
     * @param  list<int|string>  $selectedAssetIds
     * @return array<int, string>
     */
    protected static function assetOptions(?AssetTransferDocumentType $type, mixed $fromUserId, array $selectedAssetIds, ?string $search = null): array
    {
        if (! $type || (! $type->dispatchesFromStock() && ! $fromUserId)) {
            return [];
        }

        return static::assetQuery($type, $fromUserId)
            ->when($selectedAssetIds !== [], fn (Builder $query) => $query->whereNotIn('id', $selectedAssetIds))
            ->when(filled($search), fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(static::OPTIONS_LIMIT)
            ->pluck('name', 'id')
            ->all();
    }
}
