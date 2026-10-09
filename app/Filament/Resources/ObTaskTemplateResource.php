<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ObTaskTemplateResource\Pages;
use App\Models\ObChecksheet;
use App\Models\ObTaskTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ObTaskTemplateResource extends Resource
{
    protected static ?string $model = ObTaskTemplate::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Master Data';

    public static function getModelLabel(): string
    {
        return __('Template Tugas OB');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Template Tugas OB');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('name')
                    ->label('Nama Template')
                    ->placeholder('Contoh: Shift Pagi Petra')
                    ->required()
                    ->maxLength(255),

                TextInput::make('shift_label')
                    ->label('Keterangan Shift')
                    ->placeholder('Contoh: Shift Pagi (06.00-15.00)')
                    ->helperText('Hanya keterangan, tidak membatasi jam kerja OB.')
                    ->datalist(['Shift Pagi', 'Shift Middle'])
                    ->maxLength(255),

                Textarea::make('description')
                    ->label('Catatan')
                    ->rows(2),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),

                Repeater::make('items')
                    ->label('Daftar Ruangan / Pekerjaan')
                    ->relationship('items')
                    ->orderColumn('sort_order')
                    ->schema([
                        TextInput::make('room')
                            ->label('Nama Ruangan / Pekerjaan')
                            ->datalist(fn (): array => self::roomSuggestions())
                            ->required()
                            ->maxLength(255),
                    ])
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah ruangan')
                    ->itemLabel(fn (array $state): ?string => $state['room'] ?? null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('shift_label')
                    ->label('Shift')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('items_count')
                    ->label('Jumlah Ruangan')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageObTaskTemplates::route('/'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function roomSuggestions(): array
    {
        return ObChecksheet::query()
            ->whereNotNull('room')
            ->where('room', '!=', '')
            ->distinct()
            ->orderBy('room')
            ->limit(200)
            ->pluck('room')
            ->all();
    }
}
