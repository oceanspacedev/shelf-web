<?php

namespace App\Filament\Resources;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\PublicAssetRequestResource\Pages;
use App\Models\PublicAssetRequest;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class PublicAssetRequestResource extends Resource
{
    protected static ?string $model = PublicAssetRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Pengajuan Asset';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('request_type')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'pengadaan_aset' => 'Pengadaan Aset',
                        'perbaikan_aset' => 'Perbaikan Aset',
                        'penarikan_aset' => 'Penarikan Aset',
                    ])
                    ->disabled(),
                TextInput::make('requester_name')
                    ->label('Nama Pemohon')
                    ->disabled(),
                TextInput::make('email')
                    ->label('Email')
                    ->disabled(),
                TextInput::make('division')
                    ->label('Divisi')
                    ->disabled(),
                TextInput::make('placement')
                    ->label('Penempatan')
                    ->disabled(),
                TextInput::make('item_name')
                    ->label('Nama Barang')
                    ->disabled(),
                TextInput::make('qty')
                    ->label('Qty')
                    ->disabled(),
                Placeholder::make('attachment_file')
                    ->label('Lampiran')
                    ->content(function (?PublicAssetRequest $record): HtmlString|string {
                        if (! $record?->attachment_url) {
                            return '-';
                        }

                        return new HtmlString(
                            '<a href="'.$record->attachment_url.'" target="_blank" rel="noopener noreferrer">'.
                            e($record->attachment_label).
                            '</a>'
                        );
                    })
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Status')
                    ->options(RequestStatus::options())
                    ->disabled(),
                Textarea::make('admin_notes')
                    ->label('Catatan Admin')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'user:id,name',
                'asset:id,name',
                'approvals',
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_type')
                    ->label('Jenis Pengajuan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::getRequestTypeLabel($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('requester_name')
                    ->label('Pemohon')
                    ->searchable(['requester_name', 'email'])
                    ->description(fn (PublicAssetRequest $record): string => $record->email)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('division')
                    ->label('Divisi')
                    ->badge()
                    ->searchable()
                    ->description(fn (PublicAssetRequest $record): string => $record->placement)
                    ->toggleable(),
                TextColumn::make('item_name')
                    ->label('Item')
                    ->searchable()
                    ->description(fn (PublicAssetRequest $record): string => 'Qty: '.$record->qty)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('attachment_original_name')
                    ->label('Lampiran')
                    ->getStateUsing(fn (PublicAssetRequest $record): string => $record->attachment_label ? 'Buka Lampiran' : '-')
                    ->url(fn (PublicAssetRequest $record): ?string => $record->attachment_url, true)
                    ->color(fn (PublicAssetRequest $record): ?string => $record->attachment_url ? 'primary' : null)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                    ->color(fn (RequestStatus $state): string => $state->color())
                    ->description(fn (PublicAssetRequest $record): string => self::getApprovalSummary($record))
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Ref. User')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('asset.name')
                    ->label('Ref. Asset')
                    ->placeholder('-')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Diajukan')
                    ->dateTime('d M Y H:i')
                    ->description(fn (PublicAssetRequest $record): ?string => $record->created_at?->diffForHumans())
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('request_type')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'pengadaan_aset' => 'Pengadaan Aset',
                        'perbaikan_aset' => 'Perbaikan Aset',
                        'penarikan_aset' => 'Penarikan Aset',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RequestStatus::options()),
                TrashedFilter::make(),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (PublicAssetRequest $record): string => static::getUrl('view', ['record' => $record])),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PublicAssetRequest $record): bool => ! $record->trashed() && self::canDeleteRecord($record)),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn (PublicAssetRequest $record): bool => $record->trashed() && self::canDeleteRecord($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make([
                    'default' => 1,
                    'xl' => 3,
                ])
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                Section::make('Informasi Pengajuan')
                                    ->description('Data pemohon dan detail kebutuhan yang diisi dari form publik.')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                        ])
                                            ->schema([
                                                TextEntry::make('requester_name')
                                                    ->label('Nama Pemohon')
                                                    ->placeholder('-'),
                                                TextEntry::make('email')
                                                    ->label('Email')
                                                    ->placeholder('-'),
                                                TextEntry::make('division')
                                                    ->label('Divisi')
                                                    ->placeholder('-'),
                                                TextEntry::make('placement')
                                                    ->label('Penempatan')
                                                    ->placeholder('-'),
                                                TextEntry::make('item_name')
                                                    ->label('Nama Barang')
                                                    ->placeholder('-'),
                                                TextEntry::make('qty')
                                                    ->label('Qty')
                                                    ->placeholder('-'),
                                                TextEntry::make('admin_notes')
                                                    ->label('Catatan Proses')
                                                    ->placeholder('-')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columns(1),
                                Section::make('Approval')
                                    ->description('Status approval per level.')
                                    ->schema([
                                        RepeatableEntry::make('approvals')
                                            ->label('')
                                            ->grid(1)
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                ])
                                                    ->schema([
                                                        TextEntry::make('status')
                                                            ->label('Status')
                                                            ->badge()
                                                            ->formatStateUsing(fn (ApprovalStatus $state): string => $state->label())
                                                            ->color(fn (ApprovalStatus $state): string => $state->color()),
                                                        TextEntry::make('responded_at')
                                                            ->label('Waktu Respons')
                                                            ->placeholder('Belum ada respons')
                                                            ->dateTime('d M Y H:i'),
                                                        TextEntry::make('approver_name')
                                                            ->label('Penyetuju'),
                                                        TextEntry::make('approver_email')
                                                            ->label('Email')
                                                            ->columnSpan([
                                                                'default' => 1,
                                                                'md' => 2,
                                                            ])
                                                            ->extraAttributes(['class' => 'break-words']),
                                                        TextEntry::make('notes')
                                                            ->label('Catatan')
                                                            ->placeholder('-')
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])
                                            ->visible(fn (PublicAssetRequest $record): bool => $record->approvals->isNotEmpty()),
                                        TextEntry::make('approval_history_empty')
                                            ->label('Approval')
                                            ->state('Belum ada approval.')
                                            ->visible(fn (PublicAssetRequest $record): bool => $record->approvals->isEmpty()),
                                    ])
                                    ->columns(1),
                            ])
                            ->columnSpan(2),
                        Grid::make(1)
                            ->schema([
                                Section::make('Ringkasan Pengajuan')
                                    ->description('Informasi utama yang paling sering dibutuhkan saat meninjau pengajuan.')
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                TextEntry::make('request_type')
                                                    ->label('Jenis Pengajuan')
                                                    ->badge()
                                                    ->formatStateUsing(fn (string $state): string => self::getRequestTypeLabel($state)),
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->badge()
                                                    ->formatStateUsing(fn (RequestStatus $state): string => $state->label())
                                                    ->color(fn (RequestStatus $state): string => $state->color()),
                                            ]),
                                    ])
                                    ->columns(1),
                                Section::make('Informasi Pendukung')
                                    ->description('Lampiran, referensi internal, dan timestamp yang mendukung proses review.')
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                TextEntry::make('attachment_original_name')
                                                    ->label('Lampiran')
                                                    ->getStateUsing(fn (PublicAssetRequest $record): string => $record->attachment_label ?? 'Tidak ada lampiran')
                                                    ->url(fn (PublicAssetRequest $record): ?string => $record->attachment_url)
                                                    ->openUrlInNewTab(),
                                                TextEntry::make('user_reference')
                                                    ->label('Referensi User')
                                                    ->state(fn (PublicAssetRequest $record): string => $record->user
                                                        ? "{$record->user->name} (ID {$record->user->id})"
                                                        : '-'),
                                                TextEntry::make('asset_reference')
                                                    ->label('Referensi Asset')
                                                    ->state(fn (PublicAssetRequest $record): string => $record->asset
                                                        ? "{$record->asset->name} (ID {$record->asset->id})"
                                                        : '-'),
                                            ]),
                                    ])
                                    ->columns(1),
                                Section::make('Data Teknis')
                                    ->description('Identitas sistem dan metadata teknis yang jarang dipakai, tapi tetap tersedia.')
                                    ->schema([
                                        Grid::make([
                                            'default' => 1,
                                            'md' => 2,
                                            'xl' => 1,
                                        ])
                                            ->schema([
                                                TextEntry::make('uuid')
                                                    ->label('UUID')
                                                    ->copyable()
                                                    ->copyMessage('UUID disalin')
                                                    ->copyMessageDuration(1500)
                                                    ->extraAttributes(['class' => 'break-all']),
                                                TextEntry::make('created_at')
                                                    ->label('Diajukan')
                                                    ->dateTime('d M Y H:i'),
                                                TextEntry::make('updated_at')
                                                    ->label('Diperbarui')
                                                    ->dateTime('d M Y H:i'),
                                                TextEntry::make('deleted_at')
                                                    ->label('Dihapus')
                                                    ->placeholder('-')
                                                    ->dateTime('d M Y H:i'),
                                                TextEntry::make('attachment_path')
                                                    ->label('Path Lampiran')
                                                    ->placeholder('-')
                                                    ->extraAttributes(['class' => 'break-all'])
                                                    ->columnSpanFull(),
                                            ]),
                                        RepeatableEntry::make('approvals')
                                            ->label('Metadata Approval')
                                            ->grid(1)
                                            ->schema([
                                                Grid::make([
                                                    'default' => 1,
                                                    'md' => 2,
                                                ])
                                                    ->schema([
                                                        TextEntry::make('level')
                                                            ->label('Level')
                                                            ->badge(),
                                                        TextEntry::make('approval_level_id')
                                                            ->label('Approval Level ID')
                                                            ->placeholder('-'),
                                                        TextEntry::make('created_at')
                                                            ->label('Dibuat')
                                                            ->dateTime('d M Y H:i'),
                                                        TextEntry::make('responded_at')
                                                            ->label('Direspons')
                                                            ->placeholder('Belum direspons')
                                                            ->dateTime('d M Y H:i')
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])
                                            ->visible(fn (PublicAssetRequest $record): bool => $record->approvals->isNotEmpty()),
                                    ])
                                    ->collapsible()
                                    ->collapsed()
                                    ->columns(1),
                            ])
                            ->columnSpan(1),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user:id,name',
                'asset:id,name',
                'approvals.approvalLevel',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePublicAssetRequests::route('/'),
            'view' => Pages\ViewPublicAssetRequest::route('/{record}'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Pengajuan Asset';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Pengajuan Asset';
    }

    private static function getRequestTypeLabel(string $state): string
    {
        return match ($state) {
            'pengadaan_aset' => 'Pengadaan Aset',
            'perbaikan_aset' => 'Perbaikan Aset',
            'penarikan_aset' => 'Penarikan Aset',
            default => $state,
        };
    }

    private static function getApprovalSummary(PublicAssetRequest $record): string
    {
        $currentApproval = $record->approvals
            ->first(fn ($approval) => $approval->status === ApprovalStatus::Pending);

        if ($currentApproval) {
            return "Level {$currentApproval->level} - {$currentApproval->approver_name}";
        }

        return $record->approvals->isNotEmpty()
            ? 'Semua approval telah diproses'
            : 'Tanpa approval';
    }

    public static function canDeleteRecord(PublicAssetRequest $record): bool
    {
        return $record->status === RequestStatus::Pending;
    }
}
