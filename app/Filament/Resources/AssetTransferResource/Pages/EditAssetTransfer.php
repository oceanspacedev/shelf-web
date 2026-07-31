<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditAssetTransfer extends EditRecord
{
    protected static string $resource = AssetTransferResource::class;

    protected function afterSave(): void
    {
        // applyLifecycleToAssets bersifat idempoten: aset yang sudah merefleksikan
        // transfer (mis. saat hanya mengunggah dokumen / mengoreksi nomor surat)
        // dilewati oleh guard assetAlreadyReflectsTransfer. Bila admin mengubah
        // field struktural (from_user/to_user/business_entity) pada BA yang sudah
        // ter-aplikasi, validasi ensureAssetsCanMove akan menolak -> beri pesan
        // ramah dan rollback (bukan exception mentah).
        try {
            $this->record->applyLifecycleToAssets();
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Tidak dapat menyimpan perubahan')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw new Halt;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Action::make('clear_document')
                ->label('Kosongkan Dokumen')
                ->color('warning')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Kosongkan Dokumen')
                ->modalDescription('Apakah Anda yakin ingin mengosongkan dokumen ini?')
                ->modalSubmitActionLabel('Ya, kosongkan')
                ->visible(fn (AssetTransfer $record): bool => $record->document !== null)
                ->action(function (AssetTransfer $record) {
                    if ($record->document) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($record->document);
                        $record->update(['document' => null]);
                        Notification::make()
                            ->title('Dokumen berhasil dikosongkan')
                            ->success()
                            ->send();
                    }
                }),
            Action::make('download')
                ->label('Download PDF')
                ->url(fn (AssetTransfer $record): string => route('asset-transfer.download', $record))
                ->color('info'),
        ];
    }
}
