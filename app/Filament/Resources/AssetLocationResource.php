<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetLocationResource\Pages;
use App\Models\AssetLocation;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetLocationResource extends Resource
{
    protected static ?string $model = AssetLocation::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-map-pin';

    protected static \UnitEnum|string|null $navigationGroup = 'Master Data';

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('description')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->translateLabel(),
                TextColumn::make('address')->translateLabel(),
                TextColumn::make('description')->translateLabel(),
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
            'index' => Pages\ManageAssetLocations::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('Asset Location');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Asset Locations');
    }
}
