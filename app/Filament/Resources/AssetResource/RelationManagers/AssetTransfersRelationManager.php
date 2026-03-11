<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use Asmit\ResizedColumn\HasResizableColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class AssetTransfersRelationManager extends RelationManager
{
    use HasResizableColumn;

    protected static string $relationship = 'assetTransferDetails';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('assetTransfer.letter_number'),
                TextInput::make('assetTransfer.fromUser.name'),
                TextInput::make('assetTransfer.toUser.name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('letter_number')
            ->columns([
                TextColumn::make('assetTransfer.letter_number')
                    ->translateLabel()
                    ->badge(),
                TextColumn::make('assetTransfer.fromUser.name')
                    ->translateLabel()
                    ->badge()
                    ->color('danger'),
                TextColumn::make('assetTransfer.toUser.name')
                    ->translateLabel()
                    ->badge()
                    ->color('success'),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'BERITA ACARA SERAH TERIMA',
                        'success' => 'BERITA ACARA PENGALIHAN BARANG',
                        'danger' => 'BERITA ACARA PENGEMBALIAN BARANG',
                        'secondary' => 'Unknown Status',
                    ])
                    ->getStateUsing(function ($record) {
                        return $record->assetTransfer->status;
                    }),
                TextColumn::make('assetTransfer.document')
                    ->url(fn ($record) => $record && $record->assetTransfer && $record->assetTransfer->document ? Storage::url($record->assetTransfer->document) : null, true) // Membuat kolom URL untuk unduh
                    ->openUrlInNewTab()
                    ->translateLabel()
                    ->getStateUsing(fn ($record) => $record->assetTransfer && $record->assetTransfer->document ? 'Dokumen' : '-')
                    ->icon('heroicon-o-document-text'),
                TextColumn::make('assetTransfer.created_at')
                    ->date()
                    ->label(__('Created at')),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\Action::make('createAssetTransfer')
                    ->label('Transfer Asset') // Label tombol yang akan tampil di header
                    ->url(route('filament.admin.resources.asset-transfers.create')) // URL ke halaman create
                    ->icon('heroicon-o-plus') // Ikon untuk tombol
                    ->color('success'),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
