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
use Carbon\Carbon;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid as ComponentsGrid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as ComponentSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
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

    public static function getModelLabel(): string
    {
        return __('Asset Transfer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asset Transfers');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('super_admin');

        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Card::make()
                            ->schema([
                                TextInput::make('letter_number')
                                    ->translateLabel()
                                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                                    ->extraInputAttributes(['readonly' => true]),
                                Select::make('business_entity_id')
                                    ->translateLabel()
                                    ->options(fn () => Cache::remember('business_entity_options', 300, fn () => BusinessEntity::orderBy('name')->pluck('name', 'id')))
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set(
                                        'letter_number',
                                        AssetTransfer::generateLetterNumber(BusinessEntity::find($state), null)
                                    )),
                                Select::make('from_user_id')
                                    ->relationship('fromUser', 'name')
                                    ->required()
                                    ->translateLabel()
                                    ->reactive()
                                    ->searchable()
                                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                                    ->options(function () {
                                        return User::whereDoesntHave('roles', function ($query) {
                                            $query->where('name', 'super_admin');
                                        })->pluck('name', 'id');
                                    })
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $set('to_user_id', null);
                                        $set('details', null);

                                        // Update the asset_id options based on the new from_user_id
                                        $fromUserId = $get('from_user_id');
                                        $assets = Asset::query();
                                        $details = [];

                                        if ($fromUserId) {
                                            $user = User::find($fromUserId);

                                            if ($user && $user->hasRole('general_affair')) {
                                                // GA can dispatch any asset that is currently available
                                                $assets->where('condition_status', AssetCondition::Available->value);
                                                $details = [['asset_id' => '', 'equipment' => '']];
                                            } else {
                                                $assets->where('recipient_id', $fromUserId)
                                                    ->whereIn('condition_status', AssetCondition::transferableValues())
                                                    ->notLockedForOpenRequest();

                                                $details = $assets->get()->map(function ($asset) {
                                                    return ['asset_id' => $asset->id, 'equipment' => ''];
                                                })->toArray();
                                            }
                                        }

                                        $set('details', $details);
                                    }),
                                Select::make('to_user_id')
                                    ->translateLabel()
                                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                                    ->options(function (callable $get) {
                                        $fromUserId = $get('from_user_id');
                                        $query = User::query()
                                            ->whereDoesntHave('roles', function ($query) {
                                                $query->where('name', 'super_admin');
                                            });

                                        if ($fromUserId) {
                                            $query->where('id', '!=', $fromUserId);

                                            $fromUser = User::with('roles')->find($fromUserId);

                                            if ($fromUser?->hasRole('general_affair')) {
                                                $query->whereDoesntHave('roles', function ($roleQuery) {
                                                    $roleQuery->where('name', 'general_affair');
                                                });
                                            }
                                        }

                                        return $query
                                            ->with('jobTitle') // Load the related job title
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(function ($user) {
                                                // Concatenate name and job title in the format "name - jobTitle"
                                                $jobTitle = $user->jobTitle ? $user->jobTitle->title : 'N/A'; // Default if job title is missing

                                                return [$user->id => "{$user->name} - {$jobTitle}"];
                                            });
                                    })
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->translateLabel()
                                            ->required()
                                            ->maxLength(255),
                                        Select::make('business_entity_id')
                                            ->options(fn () => Cache::remember('business_entity_options', 300, fn () => BusinessEntity::orderBy('name')->pluck('name', 'id')->toArray()))
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
                                    })
                                    ->searchable()
                                    ->required(),
                                DatePicker::make('transfer_date')
                                    ->native(false)
                                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
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
                            ->hidden(fn ($context) => $context === 'create'),
                    ])
                    ->columns(1)
                    ->columnSpan(1),
                Repeater::make('details')
                    ->relationship('details')
                    ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                    ->schema([
                        Select::make('asset_id')
                            ->reactive()
                            ->required()
                            ->translateLabel()
                            ->searchable()
                            ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin)
                            ->options(function (callable $get) {
                                $fromUserId = $get('../../from_user_id');
                                $selectedAssets = collect($get('../../details'))->pluck('asset_id')->filter()->all();
                                $query = Asset::query();

                                if ($fromUserId) {
                                    $user = User::find($fromUserId);
                                    if ($user && $user->hasRole('general_affair')) {
                                        $query->where('condition_status', AssetCondition::Available->value);
                                    } else {
                                        $query->where('recipient_id', $fromUserId)
                                            ->whereIn('condition_status', AssetCondition::transferableValues());
                                    }
                                }

                                // Kunci aset yang sedang diajukan penarikan/perbaikan (belum ditindak lanjuti)
                                $query->notLockedForOpenRequest();

                                // Exclude already selected assets
                                if (! empty($selectedAssets)) {
                                    $query->whereNotIn('id', $selectedAssets);
                                }

                                return $query->pluck('name', 'id')->toArray();
                            })
                            ->getOptionLabelUsing(function ($value) {
                                return Asset::find($value)?->name;
                            }),
                        TextInput::make('equipment')
                            ->translateLabel()
                            ->disabled(fn ($context) => $context === 'edit' && ! $isSuperAdmin),
                    ])
                    ->translateLabel()
                    ->required()
                    ->hidden(fn (callable $get) => ! $get('from_user_id')) // Hide the repeater when from_user_id is not selected
                    ->columns(2)
                    ->columnSpan(2),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('businessEntity.name') // Mengambil nama dari relasi businessEntity
                    ->translateLabel()
                    ->badge()
                    ->color(fn ($record) => $record->businessEntity->color)
                    ->getStateUsing(fn ($record) => $record->businessEntity->name)
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors(AssetTransferDocumentType::colors())
                    ->getStateUsing(function ($record) {
                        return $record->status;
                    })
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
                    ->url(fn ($record) => $record && $record->document ? Storage::url($record->document) : null, true) // Membuat kolom URL untuk unduh
                    ->openUrlInNewTab()
                    ->translateLabel()
                    ->getStateUsing(fn ($record) => $record && $record->document ? 'Dokumen' : '-')
                    ->icon('heroicon-o-document-text')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('businessEntity')->relationship('businessEntity', 'name')->translateLabel(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssetTransferDocumentType::options())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->forDocumentType($data['value'])
                        : $query),
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListAssetTransfers::route('/'),
            'create' => Pages\CreateAssetTransfer::route('/create'),
            'edit' => Pages\EditAssetTransfer::route('/{record}/edit'),
            'view' => Pages\ViewAssetTransfer::route('/{record}'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentSection::make('📄 Informasi Transfer Aset')
                    ->schema([
                        ComponentsGrid::make(2) // Membuat grid dengan 2 kolom untuk tampilan yang lebih rapi
                            ->schema([
                                TextEntry::make('letter_number')
                                    ->label('Nomor Surat')
                                    ->extraAttributes([
                                        'style' => 'font-weight: bold; color: #1a202c;', // Menggunakan styling khusus
                                    ]),
                                TextEntry::make('status')
                                    ->label('Status Transfer')
                                    ->badge() // Menambahkan Badge untuk memberikan warna berdasarkan status
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
                                    ->url(fn ($record) => $record->document ? Storage::url($record->document) : null, true)
                                    ->openUrlInNewTab()
                                    ->icon('heroicon-o-document')
                                    ->getStateUsing(fn ($record) => $record && $record->document ? 'Unduh Dokumen' : 'Tidak Ada Dokumen')
                                    ->extraAttributes(['style' => 'font-weight:bold;color:#007bff;']),
                            ]),
                    ])
                    ->columns(2) // Atur kolom agar menampilkan data dalam dua kolom
                    ->collapsible(), // Bisa diklik untuk membuka atau menutup
                ComponentSection::make('📦 Detail Aset yang Ditransfer')
                    ->schema([
                        RepeatableEntry::make('details')
                            ->schema([
                                ComponentsGrid::make(2)  // Atur dalam 2 kolom
                                    ->schema([
                                        TextEntry::make('asset.name')
                                            ->label('Nama Aset')
                                            ->extraAttributes(['style' => 'font-weight: bold;']),  // Font lebih tebal untuk nama aset
                                        TextEntry::make('equipment')
                                            ->label('Keterangan Peralatan'),
                                    ]),
                            ])
                            ->columnSpan(2),  // Luaskan kolom agar detailnya rapi
                    ])
                    ->collapsible()  // Section collapsible
                    ->columns(2), // Atur agar section ditampilkan dalam 2 kolom
            ]);
    }
}
