<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetResource\RelationManagers\AssetTransfersRelationManager;
use App\Filament\Resources\UserResource\Pages;
use App\Models\BusinessEntity;
use App\Models\JobTitle;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use STS\FilamentImpersonate\Actions\Impersonate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function form(Schema $form): Schema
    {
        $isSuperAdmin = Auth::user()->hasRole('super_admin') || Auth::user()->hasRole('admin');

        return $form
            ->schema([
                Section::make('Informasi Pengguna')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('business_entity_id')
                            ->options(fn (): array => static::businessEntityOptions())
                            ->label(__('Business Entity'))
                            ->searchable()
                            // Scoped admins must place every new user inside an entity they can
                            // reach, otherwise the record would vanish from their own list.
                            ->required(fn (string $operation): bool => $operation === 'create' && ! static::viewerHasUnrestrictedAccess())
                            ->in(fn (): array => array_keys(static::businessEntityOptions())),
                        Select::make('job_title_id')
                            ->options(JobTitle::all()->pluck('title', 'id'))
                            ->label('Job Title')
                            ->searchable(),
                        TextInput::make('employee_id')
                            ->label('Employee ID')
                            ->maxLength(64)
                            ->unique(User::class, 'employee_id', ignoreRecord: true),
                        TextInput::make('whatsapp_number')
                            ->label('Nomor WhatsApp')
                            ->tel()
                            ->placeholder('081234567890')
                            ->helperText('Dipakai untuk pengingat aset via WhatsApp dan login OTP. Kosongkan untuk menonaktifkan login WhatsApp.')
                            ->afterStateHydrated(function (TextInput $component, ?User $record) use ($isSuperAdmin): void {
                                if ($isSuperAdmin && $record && blank($record->whatsapp_number) && filled($record->whatsapp_login_number)) {
                                    $component->state($record->whatsapp_login_number);
                                }
                            })
                            ->mutateStateForValidationUsing(fn ($state) => PhoneNumber::canonical($state) ?? $state)
                            ->rules($isSuperAdmin ? ['nullable', 'regex:/^[1-9][0-9]{9,14}$/D'] : ['nullable'])
                            ->unique(User::class, 'whatsapp_login_number', ignoreRecord: true)
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? preg_replace('/[^\d+]/', '', (string) $state) : null)
                            ->maxLength(32),
                        TextInput::make('whatsapp_login_number')
                            ->hidden()
                            ->dehydrated(false),
                    ]),
                Section::make('Akses & Keamanan')
                    ->schema([
                        TextInput::make('username')
                            ->maxLength(255)
                            ->unique(User::class, 'username', ignoreRecord: true)
                            ->visible($isSuperAdmin),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignoreRecord: true)
                            ->rules(['not_regex:/[\r\n]/'])
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
                        // Only super admins grant business-entity access; admins also see
                        // this section, so it must not hinge on their own (possibly
                        // unrestricted) access.
                        Toggle::make('access_all_business_entities')
                            ->label('Akses semua badan usaha')
                            ->helperText('Termasuk badan usaha yang dibuat nanti. Matikan untuk membatasi ke badan usaha asal dan yang dicentang di bawah.')
                            ->live()
                            ->visible(fn (): bool => static::viewer()->isSuperAdmin()),
                        CheckboxList::make('accessibleBusinessEntities')
                            ->label('Akses Badan Usaha')
                            ->relationship('accessibleBusinessEntities', 'name')
                            ->helperText('Badan usaha asal pengguna selalu termasuk. Centang badan usaha lain yang boleh dilihat dan dikelola pengguna ini.')
                            ->searchable()
                            ->bulkToggleable()
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'lg' => 3,
                            ])
                            ->visible(fn (Get $get): bool => static::viewer()->isSuperAdmin() && ! $get('access_all_business_entities')),
                    ])->visible($isSuperAdmin),
            ]);
    }

    public static function table(Table $table): Table
    {
        $isSuperAdmin = Auth::user()->hasRole('super_admin');

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['roles', 'businessEntity', 'accessibleBusinessEntities']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('businessEntity.name')
                    ->translateLabel('Business Entity')
                    ->badge()
                    ->color(fn ($record) => $record->businessEntity?->color)
                    ->getStateUsing(fn ($record) => $record->businessEntity?->name)
                    ->toggleable(),
                TextColumn::make('business_entity_access')
                    ->label(__('Business Entity Access'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (User $record): array => static::businessEntityAccessLabels($record))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('jobTitle.title')->translateLabel()->sortable()->searchable()->toggleable(),
                TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->toggleable(),
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
                    ->relationship(
                        'businessEntity',
                        'name',
                        fn (Builder $query): Builder => static::viewer()->limitToAccessibleBusinessEntities($query, 'business_entities.id'),
                    )
                    ->label(__('Business Entity'))
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
                Impersonate::make()
                    ->redirectTo(fn (): string => filament()->getUrl()),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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

    /**
     * Super admins see everyone. Everyone else never sees a super admin;
     * users with access to all business entities see the rest, the others
     * only users inside the business entities they may access (plus their
     * own account).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $viewer = static::viewer();

        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        $query->whereDoesntHave('roles', function (Builder $query): void {
            $query->where('name', config('filament-shield.super_admin.name', 'super_admin'));
        });

        if ($viewer->hasUnrestrictedBusinessEntityAccess()) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($viewer): void {
            $viewer
                ->limitToAccessibleBusinessEntities($query, 'users.business_entity_id')
                ->orWhere($viewer->getQualifiedKeyName(), $viewer->getKey());
        });
    }

    protected static function viewer(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    protected static function viewerHasUnrestrictedAccess(): bool
    {
        return static::viewer()->hasUnrestrictedBusinessEntityAccess();
    }

    /**
     * Badges describing every business entity a user can reach.
     *
     * @return list<string>
     */
    protected static function businessEntityAccessLabels(User $user): array
    {
        if ($user->hasUnrestrictedBusinessEntityAccess()) {
            return ['Semua badan usaha'];
        }

        return $user->effectiveBusinessEntities()->pluck('name')->all();
    }

    /**
     * Business entities the current viewer may assign to a user.
     *
     * @return array<int, string>
     */
    protected static function businessEntityOptions(): array
    {
        return static::viewer()
            ->limitToAccessibleBusinessEntities(BusinessEntity::query()->orderBy('name'), 'business_entities.id')
            ->pluck('name', 'id')
            ->all();
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function infolist(Schema $infolist): Schema
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
                                TextEntry::make('employee_id')
                                    ->label('Employee ID')
                                    ->placeholder('-')
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
                                    ->label(__('Business Entity'))
                                    ->placeholder('-')
                                    ->columnSpan(1),
                                TextEntry::make('jobTitle.title')
                                    ->label('Job Title')
                                    ->placeholder('-')
                                    ->columnSpan(1),
                                TextEntry::make('business_entity_access')
                                    ->label(__('Business Entity Access'))
                                    ->badge()
                                    ->color('gray')
                                    ->state(fn (User $record): array => static::businessEntityAccessLabels($record))
                                    ->placeholder('-')
                                    ->columnSpan(2),
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
