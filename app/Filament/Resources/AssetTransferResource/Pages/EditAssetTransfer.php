<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Exceptions\AssetTransferException;
use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use App\Models\User;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditAssetTransfer extends EditRecord
{
    protected static string $resource = AssetTransferResource::class;

    /**
     * Perubahan BA dan mutasi asetnya disimpan bersama: bila aturan siklus
     * hidup menolak, perubahan BA ikut dibatalkan.
     */
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * Struktur BA (jenis, pihak, badan usaha, aset) sebelum disimpan.
     *
     * @var array<int, mixed>|null
     */
    protected ?array $structureBeforeSave = null;

    protected function beforeValidate(): void
    {
        // Diambil sebelum getState(), yang sudah menyimpan relasi detail.
        $this->structureBeforeSave = $this->structureOf($this->getRecord());
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        // Mengunggah scan BA, mengoreksi nomor surat, atau tanggal tidak boleh
        // memindahkan aset lagi: aset bisa saja sudah berpindah lewat BA yang
        // lebih baru.
        if ($this->structureOf($record) === $this->structureBeforeSave) {
            return;
        }

        $actor = auth()->user();

        try {
            $record->unsetRelation('details')
                ->applyLifecycleToAssets(actor: $actor instanceof User ? $actor : null);
        } catch (AssetTransferException $exception) {
            Notification::make()
                ->title('Tidak dapat menyimpan perubahan')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }

    /**
     * @return array<int, mixed>
     */
    protected function structureOf(AssetTransfer $transfer): array
    {
        return [
            $transfer->documentType()?->value,
            (int) $transfer->business_entity_id,
            (int) $transfer->from_user_id,
            (int) $transfer->to_user_id,
            $transfer->details()
                ->orderBy('asset_id')
                ->pluck('asset_id')
                ->map(fn ($assetId): int => (int) $assetId)
                ->all(),
        ];
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
