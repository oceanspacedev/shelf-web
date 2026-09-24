<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetQrLabelHistoryResource\Pages;
use App\Models\AssetQrLabelHistory;
use App\Services\AssetQrLabelHistoryService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetQrLabelHistoryResource extends Resource
{
    protected static ?string $model = AssetQrLabelHistory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'History Label QR';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'History Label QR';
    }

    public static function getPluralModelLabel(): string
    {
        return 'History Label QR';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('view_any_asset')
            || $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit']);
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Detail Generate Label QR')
                ->schema([
                    TextEntry::make('created_at')
                        ->label('Waktu')
                        ->dateTime('d/m/Y H:i:s'),
                    TextEntry::make('action')
                        ->label('Aksi')
                        ->badge()
                        ->formatStateUsing(fn (AssetQrLabelHistory $record): string => $record->actionLabel())
                        ->color(fn (string $state): string => match ($state) {
                            AssetQrLabelHistory::ACTION_PRINT => 'info',
                            AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'success',
                            default => 'gray',
                        }),
                    TextEntry::make('user.name')
                        ->label('Di-generate oleh')
                        ->placeholder('—'),
                    TextEntry::make('asset_count')
                        ->label('Jumlah aset'),
                    TextEntry::make('file_name')
                        ->label('File PDF')
                        ->placeholder('Tidak ada file'),
                    TextEntry::make('asset_summary')
                        ->label('Daftar aset')
                        ->columnSpanFull()
                        ->placeholder('—'),
                    TextEntry::make('asset_ids')
                        ->label('ID aset')
                        ->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : (string) $state)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['user']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('action')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (AssetQrLabelHistory $record): string => $record->actionLabel())
                    ->color(fn (string $state): string => match ($state) {
                        AssetQrLabelHistory::ACTION_PRINT => 'info',
                        AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('asset_count')
                    ->label('Jumlah')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('asset_summary')
                    ->label('Aset')
                    ->searchable()
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (AssetQrLabelHistory $record): ?string => $record->asset_summary),
                TextColumn::make('file_name')
                    ->label('File')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Di-generate oleh')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        AssetQrLabelHistory::ACTION_PRINT => 'Print Label QR',
                        AssetQrLabelHistory::ACTION_DOWNLOAD_PDF => 'Download Label QR (PDF)',
                    ]),
                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('downloadFile')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn (AssetQrLabelHistory $record): bool => $record->hasStoredFile())
                    ->action(fn (AssetQrLabelHistory $record) => app(AssetQrLabelHistoryService::class)->download($record)),
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetQrLabelHistories::route('/'),
            'view' => Pages\ViewAssetQrLabelHistory::route('/{record}'),
        ];
    }
}
