<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApprovalLevelResource\Pages;
use App\Models\ApprovalLevel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApprovalLevelResource extends Resource
{
    protected static ?string $model = ApprovalLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Konfigurasi Approval';

    protected static ?string $navigationGroup = 'Pengaturan';

    protected static ?int $navigationSort = 10;

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
                    ->required()
                    ->searchable()
                    ->columnSpanFull(),
                TextInput::make('division')
                    ->label('Divisi')
                    ->maxLength(255)
                    ->placeholder('Contoh: Finance, Operations')
                    ->helperText('Isi sesuai nilai divisi di form asset-requests. Kosongkan jika berlaku untuk semua divisi.')
                    ->afterStateHydrated(function (TextInput $component, ?string $state): void {
                        $component->state($state === ApprovalLevel::ALL_DIVISIONS ? '' : $state);
                    })
                    ->dehydrateStateUsing(fn (?string $state): string => trim((string) $state))
                    ->columnSpanFull(),
                TextInput::make('level')
                    ->label('Level Approval')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText('Urutan persetujuan (1 = pertama, 2 = kedua, dst.)')
                    ->columnSpanFull(),
                TextInput::make('approver_name')
                    ->label('Nama / Jabatan Approver')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Contoh: Manager Operasional')
                    ->columnSpanFull(),
                TextInput::make('approver_email')
                    ->label('Email Approver')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->placeholder('approver@perusahaan.com')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderBy('request_type')
                ->orderBy('division')
                ->orderBy('level'))
            ->defaultGroup('request_type')
            ->columns([
                TextColumn::make('request_type')
                    ->label('Jenis Pengajuan')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pengadaan_aset' => 'Pengadaan Aset',
                        'perbaikan_aset' => 'Perbaikan Aset',
                        'penarikan_aset' => 'Penarikan Aset',
                        default => $state,
                    }),
                TextColumn::make('division')
                    ->label('Divisi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === ApprovalLevel::ALL_DIVISIONS ? 'Semua Divisi' : $state)
                    ->searchable(),
                TextColumn::make('level')
                    ->label('Level')
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('approver_name')
                    ->label('Nama Approver')
                    ->searchable(),
                TextColumn::make('approver_email')
                    ->label('Email')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('request_type')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'pengadaan_aset' => 'Pengadaan Aset',
                        'perbaikan_aset' => 'Perbaikan Aset',
                        'penarikan_aset' => 'Penarikan Aset',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->modalWidth('md'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageApprovalLevels::route('/'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Level Approval';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Konfigurasi Level Approval';
    }
}
