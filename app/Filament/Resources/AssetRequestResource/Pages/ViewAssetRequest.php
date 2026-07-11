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
use Filament\Resources\Pages\ViewRecord;

class ViewAssetRequest extends ViewRecord
{
    protected static string $resource = AssetRequestResource::class;

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

            Actions\Action::make('openPublicProgress')
                ->label('Lihat Progress Publik')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn (): string => $this->getRecord()->publicProgressUrl())
                ->openUrlInNewTab(),

            Actions\Action::make('resendApprovalNotification')
                ->label('Kirim Ulang Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Kirim ulang notifikasi approval?')
                ->modalDescription('Notifikasi akan dikirim ulang hanya ke approver pada level pending saat ini.')
                ->visible(fn () => $this->getRecord()->status === RequestStatus::Pending
                    && $this->getRecord()->currentPendingApproval() !== null)
                ->action(function () {
                    try {
                        $result = $this->getRecord()->sendCurrentApprovalReminder();

                        Notification::make()
                            ->title('Notifikasi approval dikirim ulang')
                            ->body('Dikirim ke '.$result['recipient']->name.'.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal mengirim ulang approval')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('resendRequesterNotification')
                ->label('Kirim Ulang ke Pengaju')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Kirim ulang progress ke pengaju?')
                ->modalDescription('Pengaju akan menerima update status terbaru beserta link progress publik.')
                ->visible(fn () => $this->getRecord()->user()->exists())
                ->action(function () {
                    try {
                        $result = $this->getRecord()->sendRequesterProgressReminder();

                        Notification::make()
                            ->title('Progress dikirim ulang ke pengaju')
                            ->body('Dikirim ke '.$result['recipient']->name.'.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Gagal mengirim ulang ke pengaju')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),

            // ---- Tindak lanjut operator (bridge) ----

            Actions\Action::make('fulfillPengadaan')
                ->label('Lanjutkan: Buat Aset')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Pengadaan))
                ->url(fn (): string => AssetResource::getUrl('create', array_filter([
                    'asset_request_id' => $this->getRecord()->id,
                    'asset_request_item_id' => $this->getRecord()->nextUnfulfilledPengadaanItem()?->id,
                ]))),

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

    protected function canFulfill(AssetRequestType $type): bool
    {
        $record = $this->getRecord();

        return $record->status === RequestStatus::Approved
            && ! $record->is_fulfilled
            && $record->type === $type;
    }
}
