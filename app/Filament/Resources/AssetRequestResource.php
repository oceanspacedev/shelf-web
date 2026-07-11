<?php

namespace App\Filament\Resources;

use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource\Pages;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\AssetRequest;
use App\Models\AssetRequestApproval;
use App\Models\AssetRequestItem;
use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Grid as InfolistGrid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Schemas\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AssetRequestResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = AssetRequest::class;

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'export',
            'restore',
            'restore_any',
            'force_delete',
            'force_delete_any',
        ];
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getModelLabel(): string
    {
        return __('Asset Request');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asset Requests');
    }

    protected static function lifecycleSummaryHtml(AssetRequest $record): HtmlString
    {
        $record->loadMissing(['asset', 'assetTransfer', 'createdAssets']);

        $rows = [
            '<div><strong>Tahap:</strong> '.e($record->lifecycleStageLabel()).'</div>',
            '<div><strong>Langkah berikutnya:</strong> '.e($record->nextStepLabel()).'</div>',
            '<div><strong>Item pengajuan:</strong> '.e($record->itemSummaryLabel()).'</div>',
            '<div><strong>Link publik:</strong> <a href="'.e($record->publicProgressUrl()).'" target="_blank" rel="noopener noreferrer" style="color: #2563eb; text-decoration: underline;">Buka progress pengajuan</a></div>',
        ];

        if ($record->asset) {
            $assetUrl = AssetResource::getUrl('view', ['record' => $record->asset]);
            $assetLabel = e($record->asset->name);
            $conditionLabel = e($record->asset->condition_status?->label() ?? '-');
            $rows[] = "<div><strong>Aset terkait:</strong> <a href=\"{$assetUrl}\" style=\"color: #2563eb; text-decoration: underline;\">{$assetLabel}</a> ({$conditionLabel})</div>";
        }

        if ($record->type === AssetRequestType::Pengadaan && $record->createdAssets->isNotEmpty()) {
            $assetsLinks = $record->createdAssets
                ->map(function ($asset): string {
                    $url = AssetResource::getUrl('view', ['record' => $asset]);
                    $name = e($asset->name);

                    return "<a href=\"{$url}\" style=\"color: #2563eb; text-decoration: underline;\">{$name}</a>";
                })
                ->implode(', ');

            $rows[] = "<div><strong>Aset dibuat:</strong> {$assetsLinks}</div>";
            $rows[] = '<div><strong>Dokumen:</strong> <a href="'.e(route('pengadaan.download', $record)).'" style="color: #2563eb; text-decoration: underline;">Download BA Pengadaan</a></div>';
        }

        if ($record->assetTransfer) {
            $transferUrl = AssetTransferResource::getUrl('view', ['record' => $record->assetTransfer]);
            $letterNumber = e($record->assetTransfer->letter_number ?? 'Transfer');
            $rows[] = "<div><strong>BA Pengembalian:</strong> <a href=\"{$transferUrl}\" style=\"color: #2563eb; text-decoration: underline;\">{$letterNumber}</a></div>";
        }

        return new HtmlString('<div class="space-y-1 text-sm">'.implode('', $rows).'</div>');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        // Left Column (width 1): Informasi Pemohon
                        Section::make('Informasi Pemohon')
                            ->schema([
                                Forms\Components\TextInput::make('reference_number')
                                    ->label('Nomor Referensi')
                                    ->placeholder('REQ-YYYY-XXX')
                                    ->readOnly()
                                    ->disabledOn('create'),
                                Forms\Components\Select::make('user_id')
                                    ->relationship('user', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                                    ->label('Nama Pemohon')
                                    ->default(fn () => auth()->id())
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                    ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable())
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Select::make('business_entity_id')
                                            ->label('Perusahaan')
                                            ->options(fn () => Cache::remember('business_entity_options', 300, fn () => BusinessEntity::orderBy('name')->pluck('name', 'id')->toArray()))
                                            ->searchable(),
                                        Forms\Components\Select::make('job_title_id')
                                            ->label('Jabatan')
                                            ->options(fn () => Cache::remember('job_title_options', 300, fn () => JobTitle::orderBy('title')->pluck('title', 'id')->toArray()))
                                            ->searchable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('title')
                                                    ->label('Nama Jabatan')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $jobTitle = JobTitle::create([
                                                    'title' => $data['title'],
                                                ]);
                                                Cache::forget('job_title_options');

                                                return $jobTitle->id;
                                            }),
                                        Forms\Components\TextInput::make('whatsapp_number')
                                            ->label('No. WhatsApp')
                                            ->placeholder('Contoh: 08123456789')
                                            ->maxLength(255),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $user = User::create([
                                            'name' => $data['name'],
                                            'business_entity_id' => $data['business_entity_id'] ?? null,
                                            'job_title_id' => $data['job_title_id'] ?? null,
                                            'whatsapp_number' => $data['whatsapp_number'] ?? null,
                                        ]);
                                        Cache::forget('user_options');

                                        return $user->id;
                                    }),
                                Forms\Components\Select::make('division_id')
                                    ->relationship('division', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                                    ->label('Divisi')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                    ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable()),
                                Forms\Components\Select::make('asset_location_id')
                                    ->relationship('assetLocation', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                                    ->label('Lokasi')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                    ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable())
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Nama Lokasi')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('address')
                                            ->label('Alamat')
                                            ->maxLength(255),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Deskripsi')
                                            ->maxLength(255),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        $location = AssetLocation::create([
                                            'name' => $data['name'],
                                            'address' => $data['address'] ?? null,
                                            'description' => $data['description'] ?? null,
                                        ]);
                                        Cache::forget('asset_location_options');

                                        return $location->id;
                                    }),
                            ])
                            ->columns(1)
                            ->columnSpan(1),

                        // Right Column (width 2): Detail Pengajuan, Lampiran, Status & Catatan
                        Grid::make(1)
                            ->schema([
                                Section::make('Detail Pengajuan')
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label('Jenis Pengajuan')
                                            ->options([
                                                'penarikan' => 'Penarikan Aset',
                                                'perbaikan' => 'Perbaikan Aset',
                                                'pengadaan' => 'Pengadaan Aset Baru',
                                            ])
                                            ->default('pengadaan')
                                            ->required()
                                            ->live()
                                            ->columnSpanFull()
                                            ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                            ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable()),
                                        Forms\Components\Repeater::make('request_items')
                                            ->label('Daftar Item / Aset')
                                            ->schema([
                                                Forms\Components\Select::make('asset_id')
                                                    ->relationship('asset', 'name', modifyQueryUsing: fn ($query, Get $get) => $query->with('recipient')->eligibleForPenarikanOrPerbaikan()->orderBy('name')->where('recipient_id', $get('../../user_id') ?: -1))
                                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name}".($record->serial_number ? " (SN: {$record->serial_number})" : '').($record->recipient ? " - Pemegang: {$record->recipient->name}" : ' - (Di GA / Tidak Digunakan)'))
                                                    ->label('Pilih Aset')
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->distinct()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                    ->visible(fn (Get $get) => in_array($get('../../type'), ['penarikan', 'perbaikan']))
                                                    ->columnSpanFull()
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                        if ($state) {
                                                            $asset = Asset::with('recipient')->find($state);
                                                            if ($asset && $asset->recipient_id) {
                                                                $set('../../user_id', $asset->recipient_id);
                                                            }
                                                        }
                                                    }),
                                                Forms\Components\TextInput::make('item_name')
                                                    ->label('Nama Aset Baru')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->visible(fn (Get $get) => $get('../../type') === 'pengadaan'),
                                                Forms\Components\TextInput::make('qty')
                                                    ->label('Jumlah')
                                                    ->numeric()
                                                    ->default(1)
                                                    ->required()
                                                    ->minValue(1)
                                                    ->visible(fn (Get $get) => $get('../../type') === 'pengadaan'),
                                            ])
                                            ->minItems(1)
                                            ->columnSpanFull()
                                            ->columns(2)
                                            // Item hanya bisa diubah saat status Pending. Setelah disetujui,
                                            // mengubah item akan merusak tracking fulfillment (fulfilled_asset_id
                                            // dst.) dan mengubah scope pengajuan tanpa re-approval.
                                            ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                            ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable()),
                                        Forms\Components\Textarea::make('description')
                                            ->label('Keterangan / Keperluan')
                                            ->maxLength(65535)
                                            ->columnSpanFull()
                                            ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                            ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable()),
                                    ])
                                    ->columns(2),

                                Section::make('Lampiran')
                                    ->schema([
                                        Forms\Components\FileUpload::make('attachment')
                                            ->label('Lampiran / Dokumen Pendukung')
                                            ->directory('asset-requests')
                                            ->visibility('public')
                                            ->multiple()
                                            ->required()
                                            ->minFiles(1)
                                            ->columnSpanFull()
                                            ->disabled(fn (?AssetRequest $record): bool => $record !== null && ! $record->isMaterialScopeEditable())
                                            ->dehydrated(fn (?AssetRequest $record): bool => $record === null || $record->isMaterialScopeEditable()),
                                    ]),
                                Section::make('Status & Catatan')
                                    ->visible(fn ($record) => $record !== null)
                                    ->schema([
                                        Forms\Components\TextInput::make('status')
                                            ->label('Status')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->formatStateUsing(fn ($record) => $record?->status?->label()),
                                        Forms\Components\Textarea::make('notes')
                                            ->label('Catatan / Alasan Penolakan')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpan(2),
                    ]),

                Section::make('Lifecycle Pengajuan')
                    ->visible(fn ($record) => $record !== null)
                    ->schema([
                        Forms\Components\Placeholder::make('lifecycle_summary')
                            ->label('Benang Merah')
                            ->content(fn (AssetRequest $record) => self::lifecycleSummaryHtml($record)),
                    ]),

                Section::make('Alur Persetujuan (Approval Tracking)')
                    ->visible(fn ($record) => $record !== null && $record->approvals()->exists())
                    ->schema([
                        Forms\Components\Repeater::make('approvals')
                            ->relationship('approvals')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->relationship('user', 'name')
                                    ->label('Approver')
                                    ->disabled(),
                                Forms\Components\TextInput::make('level')
                                    ->label('Level')
                                    ->disabled(),
                                Forms\Components\TextInput::make('status')
                                    ->label('Status')
                                    ->disabled()
                                    ->formatStateUsing(fn ($state) => is_object($state) && method_exists($state, 'label') ? $state->label() : (is_string($state) ? ucfirst($state) : $state)),
                                Forms\Components\Textarea::make('notes')
                                    ->label('Catatan / Alasan')
                                    ->disabled()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->columns([
                'lg' => 3,
            ])
            ->schema([
                InfolistGrid::make(1)
                    ->schema([
                        InfolistSection::make('Ringkasan Pengajuan')
                            ->schema([
                                InfolistGrid::make(3)
                                    ->schema([
                                        TextEntry::make('reference_number')
                                            ->label('Nomor Referensi')
                                            ->icon('heroicon-o-hashtag')
                                            ->copyable()
                                            ->placeholder('—'),
                                        TextEntry::make('type')
                                            ->label('Jenis Pengajuan')
                                            ->icon('heroicon-o-clipboard-document-list')
                                            ->formatStateUsing(fn ($state): string => $state instanceof AssetRequestType ? $state->label() : ucfirst((string) $state))
                                            ->badge()
                                            ->color(fn ($state): string => $state instanceof AssetRequestType ? $state->color() : 'gray'),
                                        TextEntry::make('status')
                                            ->label('Status Approval')
                                            ->icon('heroicon-o-shield-check')
                                            ->formatStateUsing(fn ($state): string => self::formatRequestStatus($state))
                                            ->badge()
                                            ->color(fn ($state): string => self::requestStatusColor($state)),
                                        TextEntry::make('lifecycle_stage_label')
                                            ->label('Tahap Lifecycle')
                                            ->icon('heroicon-o-arrow-path-rounded-square')
                                            ->state(fn (AssetRequest $record): string => $record->lifecycleStageLabel())
                                            ->badge()
                                            ->color(fn (AssetRequest $record): string => $record->lifecycleStageColor()),
                                        TextEntry::make('created_at')
                                            ->label('Tanggal Pengajuan')
                                            ->icon('heroicon-o-calendar')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('updated_at')
                                            ->label('Terakhir Diperbarui')
                                            ->icon('heroicon-o-clock')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('—'),
                                    ]),
                            ])
                            ->collapsible(),

                        InfolistSection::make('Pemohon & Kebutuhan')
                            ->schema([
                                InfolistGrid::make(3)
                                    ->schema([
                                        TextEntry::make('user.name')
                                            ->label('Nama Pemohon')
                                            ->icon('heroicon-o-user')
                                            ->placeholder('—'),
                                        TextEntry::make('user.email')
                                            ->label('Email')
                                            ->icon('heroicon-o-envelope')
                                            ->placeholder('—'),
                                        TextEntry::make('user.whatsapp_number')
                                            ->label('WhatsApp')
                                            ->icon('heroicon-o-device-phone-mobile')
                                            ->placeholder('—'),
                                        TextEntry::make('division.name')
                                            ->label('Divisi')
                                            ->icon('heroicon-o-building-office-2')
                                            ->placeholder('—'),
                                        TextEntry::make('assetLocation.name')
                                            ->label('Lokasi')
                                            ->icon('heroicon-o-map-pin')
                                            ->placeholder('—'),
                                        TextEntry::make('description')
                                            ->label('Keterangan / Keperluan')
                                            ->icon('heroicon-o-document-text')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->collapsible(),

                        InfolistSection::make('Item Pengajuan')
                            ->schema([
                                RepeatableEntry::make('items')
                                    ->label('Daftar Item / Aset')
                                    ->schema([
                                        InfolistGrid::make(3)
                                            ->schema([
                                                TextEntry::make('display_name')
                                                    ->label('Item / Aset')
                                                    ->state(fn (AssetRequestItem $record): string => $record->asset?->name ?? $record->item_name ?? '—')
                                                    ->icon('heroicon-o-cube')
                                                    ->columnSpan([
                                                        'default' => 'full',
                                                        'lg' => 2,
                                                    ]),
                                                TextEntry::make('qty')
                                                    ->label('Jumlah')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('1'),
                                                TextEntry::make('asset.serial_number')
                                                    ->label('Serial Number')
                                                    ->icon('heroicon-o-identification')
                                                    ->placeholder('—')
                                                    ->columnSpan([
                                                        'default' => 'full',
                                                        'lg' => 1,
                                                    ]),
                                                TextEntry::make('asset.recipient.name')
                                                    ->label('Pemegang Aset')
                                                    ->icon('heroicon-o-user')
                                                    ->placeholder('—')
                                                    ->columnSpan([
                                                        'default' => 'full',
                                                        'lg' => 2,
                                                    ]),
                                            ]),
                                    ])
                                    ->columns(1),
                            ])
                            ->collapsible(),

                        InfolistSection::make('Lampiran & Catatan')
                            ->schema([
                                InfolistGrid::make(2)
                                    ->schema([
                                        TextEntry::make('attachment')
                                            ->label('Lampiran')
                                            ->icon('heroicon-o-paper-clip')
                                            ->state(fn (AssetRequest $record): string => self::attachmentSummary($record))
                                            ->placeholder('—'),
                                        TextEntry::make('notes')
                                            ->label('Catatan Status')
                                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                            ->placeholder('—'),
                                    ]),
                            ])
                            ->collapsible(),
                    ])
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 2,
                    ]),

                InfolistGrid::make(1)
                    ->schema([
                        InfolistSection::make('Approval Tracking')
                            ->schema([
                                RepeatableEntry::make('approvals')
                                    ->label('Riwayat Approval')
                                    ->schema([
                                        InfolistGrid::make(2)
                                            ->schema([
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->icon('heroicon-o-check-circle')
                                                    ->formatStateUsing(fn ($state): string => self::formatRequestStatus($state))
                                                    ->badge()
                                                    ->color(fn ($state): string => self::requestStatusColor($state)),
                                                TextEntry::make('user.name')
                                                    ->label('Approver')
                                                    ->icon('heroicon-o-user')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                                TextEntry::make('public_token')
                                                    ->label('Link Approval Publik')
                                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                                    ->formatStateUsing(fn (): string => 'Buka halaman approval')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->url(fn ($state, ?AssetRequestApproval $record): ?string => $record?->status === RequestStatus::Pending ? $record->publicApprovalUrl() : null, true)
                                                    ->visible(fn ($state, ?AssetRequestApproval $record): bool => $record?->status === RequestStatus::Pending)
                                                    ->columnSpanFull(),
                                                TextEntry::make('decidedBy.name')
                                                    ->label('Diputuskan Oleh')
                                                    ->icon('heroicon-o-user-circle')
                                                    ->placeholder('—'),
                                                TextEntry::make('decided_at')
                                                    ->label('Waktu Keputusan')
                                                    ->icon('heroicon-o-calendar-days')
                                                    ->dateTime('d M Y H:i')
                                                    ->placeholder('—'),
                                                TextEntry::make('notes')
                                                    ->label('Catatan / Alasan')
                                                    ->icon('heroicon-o-document-text')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columns(1),
                            ])
                            ->collapsible(),

                        InfolistSection::make('Tindak Lanjut Operasional')
                            ->schema([
                                InfolistGrid::make(1)
                                    ->schema([
                                        TextEntry::make('next_step_label')
                                            ->label('Langkah Berikutnya')
                                            ->icon('heroicon-o-forward')
                                            ->state(fn (AssetRequest $record): string => $record->nextStepLabel()),
                                        TextEntry::make('fulfilled_status')
                                            ->label('Status Tindak Lanjut')
                                            ->icon('heroicon-o-check-badge')
                                            ->state(fn (AssetRequest $record): string => $record->isFulfilled() ? 'Selesai' : 'Belum selesai')
                                            ->badge()
                                            ->color(fn (AssetRequest $record): string => $record->isFulfilled() ? 'success' : 'warning'),
                                        TextEntry::make('fulfilledBy.name')
                                            ->label('Ditindaklanjuti Oleh')
                                            ->icon('heroicon-o-user')
                                            ->placeholder('—'),
                                        TextEntry::make('fulfilled_at')
                                            ->label('Tanggal Tindak Lanjut')
                                            ->icon('heroicon-o-calendar')
                                            ->dateTime('d M Y H:i')
                                            ->placeholder('—'),
                                        TextEntry::make('assetTransfer.letter_number')
                                            ->label('BA Pengembalian')
                                            ->icon('heroicon-o-arrow-uturn-left')
                                            ->placeholder('—')
                                            ->url(fn (AssetRequest $record): ?string => $record->assetTransfer ? AssetTransferResource::getUrl('view', ['record' => $record->assetTransfer]) : null),
                                    ]),
                            ])
                            ->collapsible(),

                        InfolistSection::make('Link Publik')
                            ->schema([
                                InfolistGrid::make(1)
                                    ->schema([
                                        TextEntry::make('public_token')
                                            ->label('Progress Pengajuan')
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->state(fn (): string => 'Buka progress publik')
                                            ->badge()
                                            ->color('primary')
                                            ->url(fn (AssetRequest $record): string => $record->publicProgressUrl(), true)
                                            ->copyable()
                                            ->copyableState(fn (AssetRequest $record): string => $record->publicProgressUrl()),
                                        TextEntry::make('current_approval_link')
                                            ->label('Approval Pending')
                                            ->icon('heroicon-o-paper-airplane')
                                            ->state(fn (AssetRequest $record): string => $record->currentPendingApproval() ? 'Buka approval pending' : 'Tidak ada approval pending')
                                            ->badge()
                                            ->color(fn (AssetRequest $record): string => $record->currentPendingApproval() ? 'warning' : 'gray')
                                            ->url(fn (AssetRequest $record): ?string => $record->currentPendingApproval()?->publicApprovalUrl(), true)
                                            ->copyable(fn (AssetRequest $record): bool => $record->currentPendingApproval() !== null)
                                            ->copyableState(fn (AssetRequest $record): ?string => $record->currentPendingApproval()?->publicApprovalUrl()),
                                    ]),
                            ])
                            ->compact()
                            ->collapsible(),
                    ])
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 1,
                    ]),
            ]);
    }

    protected static function formatRequestStatus(mixed $state): string
    {
        if ($state instanceof RequestStatus) {
            return $state->label();
        }

        return filled($state) ? ucfirst((string) $state) : '—';
    }

    protected static function requestStatusColor(mixed $state): string
    {
        if ($state instanceof RequestStatus) {
            return $state->color();
        }

        return match ((string) $state) {
            RequestStatus::Approved->value => 'success',
            RequestStatus::Rejected->value => 'danger',
            RequestStatus::Pending->value => 'warning',
            default => 'gray',
        };
    }

    protected static function attachmentSummary(AssetRequest $record): string
    {
        $attachments = collect(is_array($record->attachment) ? $record->attachment : ($record->attachment ? [$record->attachment] : []))
            ->filter()
            ->values();

        if ($attachments->isEmpty()) {
            return '—';
        }

        if ($attachments->count() === 1) {
            return basename((string) $attachments->first());
        }

        return $attachments->count().' lampiran';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference_number')
                    ->label('Nomor Referensi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Jenis Pengajuan')
                    ->badge()
                    ->color(fn (AssetRequestType $state): string => $state->color())
                    ->formatStateUsing(fn (AssetRequestType $state): string => $state->label())
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Nama Pemohon')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('division.name')
                    ->label('Divisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('assetLocation.name')
                    ->label('Lokasi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Nama Aset')
                    ->getStateUsing(fn (AssetRequest $record): string => $record->itemSummaryLabel())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('item_name', 'like', "%{$search}%")
                            ->orWhereHas('asset', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('items', fn ($q) => $q->where('item_name', 'like', "%{$search}%")
                                ->orWhereHas('asset', fn ($assetQuery) => $assetQuery->where('name', 'like', "%{$search}%")));
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Jumlah')
                    ->numeric()
                    ->getStateUsing(fn (AssetRequest $record): int => $record->itemQuantityTotal())
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (RequestStatus $state): string => $state->color())
                    ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                    ->sortable(),
                Tables\Columns\TextColumn::make('lifecycle_stage')
                    ->label('Tahap Lifecycle')
                    ->badge()
                    ->getStateUsing(fn (AssetRequest $record): string => $record->lifecycleStageLabel())
                    ->color(fn (AssetRequest $record): string => $record->lifecycleStageColor()),
                Tables\Columns\TextColumn::make('next_step')
                    ->label('Langkah Berikutnya')
                    ->getStateUsing(fn (AssetRequest $record): string => $record->nextStepLabel())
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'penarikan' => 'Penarikan',
                        'perbaikan' => 'Perbaikan',
                        'pengadaan' => 'Pengadaan',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(RequestStatus::options()),
                Tables\Filters\SelectFilter::make('division')
                    ->label('Divisi')
                    ->relationship('division', 'name'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AssetRequest $record) => auth()->user()?->can('approve', $record) ?? false)
                    ->action(function (AssetRequest $record, array $data) {
                        $record->approveCurrentLevel($data['notes'] ?? null);

                        Notification::make()
                            ->title('Pengajuan disetujui')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan (Opsional)')
                            ->maxLength(65535),
                    ]),

                \Filament\Actions\Action::make('reject')
                    ->label('Tolak')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AssetRequest $record) => auth()->user()?->can('approve', $record) ?? false)
                    ->action(function (AssetRequest $record, array $data) {
                        $record->rejectCurrentLevel($data['notes']);

                        Notification::make()
                            ->title('Pengajuan ditolak')
                            ->success()
                            ->send();
                    })
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Alasan Penolakan (Wajib)')
                            ->required()
                            ->maxLength(65535),
                    ]),

                \Filament\Actions\Action::make('openPublicProgress')
                    ->label('Lihat Progress Publik')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (AssetRequest $record): string => $record->publicProgressUrl())
                    ->openUrlInNewTab(),

                \Filament\Actions\Action::make('resendApprovalNotification')
                    ->label('Kirim Ulang Approval')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang notifikasi approval?')
                    ->modalDescription('Notifikasi akan dikirim ulang hanya ke approver pada level pending saat ini.')
                    ->visible(fn (AssetRequest $record): bool => $record->status === RequestStatus::Pending
                        && $record->currentPendingApproval() !== null)
                    ->action(function (AssetRequest $record): void {
                        try {
                            $result = $record->sendCurrentApprovalReminder();

                            Notification::make()
                                ->title('Notifikasi approval dikirim ulang')
                                ->body('Dikirim ke '.$result['recipient']->name.'.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal mengirim ulang approval')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('resendRequesterNotification')
                    ->label('Kirim Ulang ke Pengaju')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang progress ke pengaju?')
                    ->modalDescription('Pengaju akan menerima update status terbaru beserta link progress publik.')
                    ->visible(fn (AssetRequest $record): bool => $record->user()->exists())
                    ->action(function (AssetRequest $record): void {
                        try {
                            $result = $record->sendRequesterProgressReminder();

                            Notification::make()
                                ->title('Progress dikirim ulang ke pengaju')
                                ->body('Dikirim ke '.$result['recipient']->name.'.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal mengirim ulang ke pengaju')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                \Filament\Actions\Action::make('fulfillPengadaan')
                    ->label('Lanjutkan: Buat Aset')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (AssetRequest $record): bool => $record->status === RequestStatus::Approved
                        && ! $record->is_fulfilled
                        && $record->type === AssetRequestType::Pengadaan)
                    ->url(fn (AssetRequest $record): string => AssetResource::getUrl('create', array_filter([
                        'asset_request_id' => $record->id,
                        'asset_request_item_id' => $record->nextUnfulfilledPengadaanItem()?->id,
                    ]))),

                \Filament\Actions\Action::make('fulfillPenarikan')
                    ->label('Lanjutkan: Buat BA')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->visible(fn (AssetRequest $record): bool => $record->status === RequestStatus::Approved
                        && ! $record->is_fulfilled
                        && $record->type === AssetRequestType::Penarikan)
                    ->url(fn (AssetRequest $record): string => AssetTransferResource::getUrl('create', [
                        'asset_request_id' => $record->id,
                    ])),

                \Filament\Actions\Action::make('fulfillPerbaikan')
                    ->label('Lanjutkan: Tandai Perbaikan')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Aset terkait akan ditandai Rusak (Damaged) dan status NBH menjadi Pending untuk ditindaklanjuti. Lanjutkan?')
                    ->visible(fn (AssetRequest $record): bool => $record->status === RequestStatus::Approved
                        && ! $record->is_fulfilled
                        && $record->type === AssetRequestType::Perbaikan
                        && $record->requestedAssetIds() !== [])
                    ->action(function (AssetRequest $record): void {
                        $asset = $record->fulfillPerbaikan(auth()->user());

                        Notification::make()
                            ->title('Aset ditandai untuk perbaikan')
                            ->body("Aset \"{$asset->name}\" sekarang Rusak, NBH Pending.")
                            ->warning()
                            ->send();
                    }),

                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->visible(fn () => auth()->user()->can('export', static::getModel()))
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns([
                                    Column::make('reference_number')->heading('Nomor Referensi'),
                                    Column::make('type')
                                        ->heading('Jenis Pengajuan')
                                        ->getStateUsing(fn ($record) => $record->type instanceof AssetRequestType ? $record->type->label() : ucfirst((string) $record->type)),
                                    Column::make('user.name')->heading('Nama Pemohon'),
                                    Column::make('division.name')->heading('Divisi'),
                                    Column::make('item_name')
                                        ->heading('Nama Aset')
                                        ->getStateUsing(fn (AssetRequest $record): string => $record->itemSummaryLabel()),
                                    Column::make('qty')
                                        ->heading('Jumlah')
                                        ->getStateUsing(fn (AssetRequest $record): int => $record->itemQuantityTotal()),
                                    Column::make('status')
                                        ->heading('Status')
                                        ->getStateUsing(fn ($record) => $record->status?->label()),
                                    Column::make('lifecycle_stage_label')
                                        ->heading('Tahap Lifecycle')
                                        ->getStateUsing(fn ($record) => $record->lifecycleStageLabel()),
                                    Column::make('next_step_label')
                                        ->heading('Langkah Berikutnya')
                                        ->getStateUsing(fn ($record) => $record->nextStepLabel()),
                                    Column::make('attachment')
                                        ->heading('Lampiran')
                                        ->getStateUsing(function ($record) {
                                            if (empty($record->attachment)) {
                                                return '-';
                                            }
                                            $attachments = is_array($record->attachment) ? $record->attachment : [$record->attachment];

                                            return collect($attachments)
                                                ->map(fn ($file) => Storage::disk('public')->url($file))
                                                ->implode(', ');
                                        }),
                                    Column::make('description')->heading('Keterangan'),
                                    Column::make('notes')->heading('Catatan Admin'),
                                    Column::make('created_at')->heading('Tanggal Dibuat'),
                                    Column::make('updated_at')->heading('Tanggal Diperbarui'),
                                ])
                                ->withFilename('export_asset_requests_'.date('Y-m-d')),
                        ]),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListAssetRequests::route('/'),
            'create' => Pages\CreateAssetRequest::route('/create'),
            'view' => Pages\ViewAssetRequest::route('/{record}'),
            'edit' => Pages\EditAssetRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            // Hindari N+1 pada list: kolom/visible memakai items.asset, user, division,
            // dan approvals (currentPendingApproval / can('approve')).
            ->with([
                'items.asset',
                'user',
                'division',
                'approvals' => fn ($query) => $query->orderBy('level'),
            ]);
    }
}
