<?php

namespace App\Filament\Resources\AssetRequestResource\Pages;

use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetTransferResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAssetRequest extends EditRecord
{
    protected static string $resource = AssetRequestResource::class;

    public array $requestItems = [];

    protected bool $shouldSyncItems = false;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        // items adalah source of truth setelah migrasi createLegacyItemIfNeeded()
        // (item pertama sudah dipindah ke tabel asset_request_items). Bangun repeater
        // HANYA dari items agar item pertama tidak tampil dua kali; fallback ke legacy
        // columns hanya bila belum ada baris items (request lama yang belum termigrasi).
        $requestItems = [];

        if ($record->items->isNotEmpty()) {
            foreach ($record->items as $item) {
                $requestItems[] = [
                    'asset_id' => $item->asset_id,
                    'item_name' => $item->item_name,
                    'qty' => $item->qty,
                ];
            }
        } elseif ($record->asset_id || $record->item_name) {
            $requestItems[] = [
                'asset_id' => $record->asset_id,
                'item_name' => $record->item_name,
                'qty' => $record->qty,
            ];
        }

        $data['request_items'] = $requestItems;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Saat repeater item dinonaktifkan (status != Pending), Filament tidak
        // mengirim request_items. Jangan ubah legacy/item sama sekali agar tracking
        // fulfillment (fulfilled_asset_id/fulfilled_at/notes) tidak terhapus.
        if (! array_key_exists('request_items', $data)) {
            $this->shouldSyncItems = false;

            return $data;
        }

        $items = $data['request_items'];
        unset($data['request_items']);

        $firstItem = $items[0] ?? [];
        $data['asset_id'] = $firstItem['asset_id'] ?? null;
        $data['item_name'] = $firstItem['item_name'] ?? null;
        $data['qty'] = $firstItem['qty'] ?? 1;

        $this->requestItems = array_slice($items, 1);
        $this->shouldSyncItems = true;

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->shouldSyncItems) {
            return;
        }

        $record = $this->getRecord();
        $record->items()->delete();
        foreach ($this->requestItems ?? [] as $itemRow) {
            $record->items()->create($itemRow);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()?->can('approve', $this->getRecord()) ?? false)
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $record->approveCurrentLevel($data['notes'] ?? null);
                    $this->refreshFormData(['status', 'current_level']);

                    Notification::make()
                        ->title('Pengajuan disetujui')
                        ->success()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan (Opsional)')
                        ->maxLength(65535),
                ]),

            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => auth()->user()?->can('approve', $this->getRecord()) ?? false)
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $record->rejectCurrentLevel($data['notes']);
                    $this->refreshFormData(['status']);

                    Notification::make()
                        ->title('Pengajuan ditolak')
                        ->success()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Alasan Penolakan (Wajib)')
                        ->required()
                        ->maxLength(65535),
                ]),

            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),

            // ---- Tindak lanjut operator (bridge): approval = persetujuan untuk
            // tindak lanjut; operator menjalankan tindak lanjut secara manual. ----

            Actions\Action::make('fulfillPengadaan')
                ->label('Lanjutkan: Buat Aset')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Pengadaan))
                ->url(fn (): string => AssetResource::getUrl('create', [
                    'asset_request_id' => $this->getRecord()->id,
                ])),

            Actions\Action::make('fulfillPerbaikan')
                ->label('Lanjutkan: Tandai Perbaikan')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Aset terkait akan ditandai Rusak (Damaged) dan status NBH menjadi Pending untuk ditindaklanjuti. Lanjutkan?')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Perbaikan))
                ->action(function () {
                    $record = $this->getRecord();
                    $asset = $record->fulfillPerbaikan(auth()->user());

                    Notification::make()
                        ->title('Aset ditandai untuk perbaikan')
                        ->body("Aset \"{$asset->name}\" sekarang Rusak, NBH Pending.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'fulfilled_at', 'fulfilled_by_user_id']);
                }),

            Actions\Action::make('fulfillPenarikan')
                ->label('Lanjutkan: Buat BA')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Penarikan))
                ->url(fn (): string => AssetTransferResource::getUrl('create', [
                    'asset_request_id' => $this->getRecord()->id,
                ])),

            Actions\Action::make('downloadPengadaan')
                ->label('Download BA Pengadaan')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn () => $this->getRecord()->type === AssetRequestType::Pengadaan
                    && $this->getRecord()->is_fulfilled)
                ->url(fn () => route('pengadaan.download', $this->getRecord())),
        ];
    }

    /**
     * Apakah tindak lanjut (fulfillment) tersedia untuk record saat ini:
     * harus Approved, belum fulfilled, dan bertipe sesuai.
     */
    protected function canFulfill(AssetRequestType $type): bool
    {
        $record = $this->getRecord();

        return $record->status === RequestStatus::Approved
            && ! $record->is_fulfilled
            && $record->type === $type;
    }
}
