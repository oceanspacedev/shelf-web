<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DivisionResource\Pages;
use App\Models\Division;
use App\Models\User;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DivisionResource extends Resource
{
    protected static ?string $model = Division::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Divisi')
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->maxLength(255),

                Forms\Components\Repeater::make('approvers')
                    ->label('Alur Persetujuan (Approval Flow)')
                    ->helperText('Tambahkan approver sesuai jabatan yang harus menyetujui. Seret untuk mengatur alur.')
                    ->relationship('approvers')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Approver')
                            ->relationship('user', 'name', function ($query, $get, $state) {
                                $selectedUsers = collect($get('../../approvers'))
                                    ->pluck('user_id')
                                    ->filter()
                                    ->all();

                                return $query
                                    ->with('jobTitle')
                                    ->when($selectedUsers, function ($q) use ($selectedUsers, $state) {
                                        $q->whereNotIn('id', array_diff($selectedUsers, [$state]));
                                    })
                                    ->orderBy('name');
                            })
                            ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->nameWithJobTitle())
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(),
                        Forms\Components\Hidden::make('level')
                            ->default(fn ($livewire, $get) => count($get('../../approvers') ?? []) + 1),
                    ])
                    ->columns(1)
                    ->defaultItems(1)
                    ->reorderable('level')
                    ->orderColumn('level')
                    ->itemLabel(function (array $state): ?string {
                        if (! isset($state['user_id'])) {
                            return 'Approver baru';
                        }

                        $user = User::with('jobTitle')->find($state['user_id']);

                        return $user?->nameWithJobTitle() ?? 'Approver';
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Divisi')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approvers')
                    ->label('Alur Persetujuan')
                    ->getStateUsing(function (Division $record) {
                        $record->loadMissing('approvers.user.jobTitle');

                        return $record->approvers
                            ->map(fn ($approver) => $approver->user?->nameWithJobTitle() ?? 'Unknown')
                            ->implode(' ➔ ');
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                    \Filament\Actions\ForceDeleteBulkAction::make(),
                    \Filament\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ManageDivisions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('Division');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Divisions');
    }
}
