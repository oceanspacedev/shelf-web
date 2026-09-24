<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class QrScansRelationManager extends RelationManager
{
    protected static string $relationship = 'qrScans';

    protected static ?string $title = 'Riwayat Scan QR';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('latitude')
                    ->label('Lat')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('longitude')
                    ->label('Lng')
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->placeholder('Guest'),
                Tables\Columns\TextColumn::make('user_agent')
                    ->label('Device')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
