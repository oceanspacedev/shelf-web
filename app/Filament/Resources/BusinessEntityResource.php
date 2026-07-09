<?php

namespace App\Filament\Resources;

use App\Enums\BadgeColor;
use App\Filament\Resources\BusinessEntityResource\Pages;
use App\Models\BusinessEntity;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BusinessEntityResource extends Resource
{
    protected static ?string $model = BusinessEntity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static \UnitEnum|string|null $navigationGroup = 'Master Data';

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('format')
                    ->required()
                    ->maxLength(255),
                Select::make('color')
                    ->label('Color')
                    ->allowHtml()
                    ->options(
                        collect(BadgeColor::cases())
                            ->sort(static fn ($a, $b) => $a->value <=> $b->value)
                            ->mapWithKeys(static fn ($case) => [
                                $case->value => "<span class='flex items-center gap-x-4'>
                            <span class='rounded-full w-4 h-4' style='background:rgb(".$case->getColor()[600].")'></span>
                            <span>".$case->getLabel().'</span>
                            </span>',
                            ]),
                    )
                    ->searchable()
                    ->required(),
                FileUpload::make('letterhead')
                    ->image()
                    ->label('Kop Surat')
                    ->disk('public')
                    ->directory('kopsurat'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->translateLabel('Business Entity')
                    ->searchable()
                    ->badge()
                    ->color(fn ($record) => $record->color)
                    ->getStateUsing(fn ($record) => $record->name),
                TextColumn::make('format')->translateLabel(),
                TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime(),
                TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime(),
            ])
            ->filters([
                //
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
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBusinessEntities::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Business Entity');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Business Entities');
    }
}
