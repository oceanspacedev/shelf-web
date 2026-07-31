<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetTransfer extends ViewRecord
{
    protected static string $resource = AssetTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\Action::make('clear_document')
                ->label('Kosongkan Dokumen')
                ->color('warning')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Kosongkan Dokumen')
                ->modalDescription('Apakah Anda yakin ingin mengosongkan dokumen ini?')
                ->modalSubmitActionLabel('Ya, kosongkan')
                ->visible(fn (\App\Models\AssetTransfer $record): bool => $record->document !== null)
                ->action(function (\App\Models\AssetTransfer $record) {
                    if ($record->document) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($record->document);
                        $record->update(['document' => null]);
                        \Filament\Notifications\Notification::make()
                            ->title('Dokumen berhasil dikosongkan')
                            ->success()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('download')
                ->label('Download PDF')
                ->url(fn (\App\Models\AssetTransfer $record): string => route('asset-transfer.download', $record))
                ->color('info'),
        ];
    }
}
