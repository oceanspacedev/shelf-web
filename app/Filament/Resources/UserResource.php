<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\RelationManagers\AssetTransfersRelationManager;
use App\Filament\Resources\UserResource\Pages;
use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        $isSuperAdmin = Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('admin');

        return $form
            ->schema([
                Card::make([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('business_entity_id')
                        ->options(BusinessEntity::all()->pluck('name', 'id'))
                        ->label('Business Entity')
                        ->searchable(),
                    Select::make('job_title_id')
                        ->options(JobTitle::all()->pluck('title', 'id'))
                        ->label('Job Title')
                        ->searchable(),
                    TextInput::make('whatsapp_number')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        ->placeholder('081234567890')
                        ->helperText('Dipakai untuk pengingat aset via WhatsApp/Fonnte.')
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? preg_replace('/[^\d+]/', '', (string) $state) : null)
                        ->maxLength(32),
                ]),
                Card::make([
                    TextInput::make('username')
                        ->maxLength(255)
                        ->unique(User::class, 'username', ignoreRecord: true)
                        ->visible($isSuperAdmin),
                    TextInput::make('email')
                        ->maxLength(255)
                        ->visible($isSuperAdmin),
                    TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state))
                        ->maxLength(255)
                        ->visible($isSuperAdmin),
                    DateTimePicker::make('email_verified_at')
                        ->label('Email Verified At')
                        ->visible($isSuperAdmin),
                    Select::make('roles')
                        ->label('Roles')
                        ->relationship('roles', 'name')
                        ->preload()
                        ->searchable()
                        ->visible($isSuperAdmin),
                ])->visible($isSuperAdmin),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = Auth::user()->hasRole('super_admin');

        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('businessEntity.name')
                    ->translateLabel('Business Entity')
                    ->badge()
                    ->color(fn ($record) => $record->businessEntity->color)
                    ->getStateUsing(fn ($record) => $record->businessEntity->name ?? null)
                    ->toggleable(),
                TextColumn::make('jobTitle.title')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('whatsapp_number')
                    ->label('WhatsApp')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('roles.name')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visible($isSuperAdmin),
            ])
            ->filters([
                SelectFilter::make('businessEntity')
                    ->relationship('businessEntity', 'name')
                    ->label('Business Entity')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('jobTitle')
                    ->relationship('jobTitle', 'title')
                    ->label('Job Title')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Roles')
                    ->searchable()
                    ->preload()
                    ->visible($isSuperAdmin),
            ])
            ->defaultSort('created_at', 'desc')
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
            AssetTransfersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereDoesntHave('roles', function (Builder $query) {
            $query->where('name', 'super_admin');
        });
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('User Information')
                    ->description('Details about the user')
                    ->schema([
                        Grid::make(2) // 2-column layout for better readability
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Full Name')
                                    ->columnSpan(1),
                                TextEntry::make('email')
                                    ->label('Email Address')
                                    ->columnSpan(1),
                                TextEntry::make('whatsapp_number')
                                    ->label('Nomor WhatsApp')
                                    ->placeholder('-')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Professional Information')
                    ->description('Business and Job details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('businessEntity.name')
                                    ->label('Business Entity')
                                    ->columnSpan(1),
                                TextEntry::make('jobTitle.title')
                                    ->label('Job Title')
                                    ->columnSpan(1),
                            ]),
                    ]),

                Section::make('Timestamps')
                    ->description('Creation and update times')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
