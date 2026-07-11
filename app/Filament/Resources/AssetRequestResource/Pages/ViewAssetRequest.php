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

    public function getTitle(): string
    {
        return $this->getRecord()->reference_number ?: 'Detail Pengajuan Aset';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return $record->type->label().' · '.$record->lifecycleStageLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Setujui')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui pengajuan ini?')
                ->modalDescription('Keputusan akan dicatat atas nama Anda. Jika masih ada approver berikutnya, pengajuan akan diteruskan ke tahap tersebut.')
                ->modalSubmitActionLabel('Ya, setujui')
                ->visible(fn () => $this->getRecord()->status === RequestStatus::Pending
                    && (auth()->user()?->can('approve', $this->getRecord()) ?? false))
                ->action(function (array $data): void {
                    $this->getRecord()->approveCurrentLevel($data['notes'] ?? null);

                    Notification::make()
                        ->title('Pengajuan disetujui')
                        ->body('Status dan langkah berikutnya sudah diperbarui.')
                        ->success()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan (opsional)')
                        ->helperText('Catatan akan tersimpan pada riwayat approval.')
                        ->maxLength(65535),
                ]),

            Actions\Action::make('reject')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->outlined()
                ->requiresConfirmation()
                ->modalHeading('Tolak pengajuan ini?')
                ->modalDescription('Pengajuan akan berhenti dan alasan penolakan dapat dilihat oleh pengaju.')
                ->modalSubmitActionLabel('Tolak pengajuan')
                ->visible(fn () => $this->getRecord()->status === RequestStatus::Pending
                    && (auth()->user()?->can('approve', $this->getRecord()) ?? false))
                ->action(function (array $data): void {
                    $this->getRecord()->rejectCurrentLevel($data['notes']);

                    Notification::make()
                        ->title('Pengajuan ditolak')
                        ->body('Alasan penolakan sudah dicatat dan progress pengaju diperbarui.')
                        ->warning()
                        ->send();
                })
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label('Alasan penolakan')
                        ->helperText('Jelaskan apa yang perlu diperbaiki agar pengaju memahami keputusan ini.')
                        ->required()
                        ->maxLength(65535),
                ]),

            Actions\Action::make('fulfillPengadaan')
                ->label('Buat Aset')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Pengadaan))
                ->url(fn (): string => AssetResource::getUrl('create', array_filter([
                    'asset_request_id' => $this->getRecord()->id,
                    'asset_request_item_id' => $this->getRecord()->nextUnfulfilledPengadaanItem()?->id,
                ]))),

            Actions\Action::make('fulfillPenarikan')
                ->label('Buat BA Pengembalian')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('success')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Penarikan))
                ->url(fn (): string => AssetTransferResource::getUrl('create', [
                    'asset_request_id' => $this->getRecord()->id,
                ])),

            Actions\Action::make('fulfillPerbaikan')
                ->label('Proses Perbaikan')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Proses aset untuk perbaikan?')
                ->modalDescription('Status aset akan menjadi Rusak dan NBH menjadi Pending. Pengajuan ditandai selesai setelah proses ini dijalankan.')
                ->modalSubmitActionLabel('Ya, proses perbaikan')
                ->visible(fn () => $this->canFulfill(AssetRequestType::Perbaikan))
                ->action(function (): void {
                    $asset = $this->getRecord()->fulfillPerbaikan(auth()->user());

                    Notification::make()
                        ->title('Aset masuk proses perbaikan')
                        ->body("Aset \"{$asset->name}\" sekarang Rusak, NBH Pending.")
                        ->warning()
                        ->send();
                }),

            Actions\Action::make('downloadPengadaan')
                ->label('Unduh BA Pengadaan')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->visible(fn () => $this->getRecord()->type === AssetRequestType::Pengadaan
                    && $this->getRecord()->is_fulfilled)
                ->url(fn () => route('pengadaan.download', $this->getRecord())),

            Actions\EditAction::make()
                ->label('Ubah Data')
                ->color('gray')
                ->outlined(),

            Actions\ActionGroup::make([
                Actions\Action::make('openPublicProgress')
                    ->label('Buka Progress Publik')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (): string => $this->getRecord()->publicProgressUrl())
                    ->openUrlInNewTab(),

                Actions\Action::make('resendApprovalNotification')
                    ->label('Kirim Ulang ke Approver')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Kirim ulang notifikasi ke approver?')
                    ->modalDescription('Pengingat hanya dikirim ke approver yang sedang menunggu. Keputusan approval tidak berubah.')
                    ->modalSubmitActionLabel('Kirim notifikasi')
                    ->visible(fn () => $this->getRecord()->status === RequestStatus::Pending
                        && $this->getRecord()->currentPendingApproval() !== null)
                    ->action(function (): void {
                        try {
                            $result = $this->getRecord()->sendCurrentApprovalReminder();

                            Notification::make()
                                ->title('Notifikasi approver dikirim ulang')
                                ->body('Dikirim ke '.$result['recipient']->name.'.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal mengirim ulang notifikasi approver')
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
                    ->modalDescription('Pengaju akan menerima status terbaru dan link progress publik. Status pengajuan tidak berubah.')
                    ->modalSubmitActionLabel('Kirim notifikasi')
                    ->visible(fn () => $this->getRecord()->user()->exists())
                    ->action(function (): void {
                        try {
                            $result = $this->getRecord()->sendRequesterProgressReminder();

                            Notification::make()
                                ->title('Notifikasi pengaju dikirim ulang')
                                ->body('Dikirim ke '.$result['recipient']->name.'.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal mengirim ulang notifikasi pengaju')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\DeleteAction::make()
                    ->label('Hapus pengajuan'),
                Actions\ForceDeleteAction::make()
                    ->label('Hapus permanen'),
                Actions\RestoreAction::make()
                    ->label('Pulihkan pengajuan'),
            ])
                ->label('Aksi lainnya')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
        ];
    }

    protected function canFulfill(AssetRequestType $type): bool
    {
        $record = $this->getRecord();

        return $record->status === RequestStatus::Approved
            && ! $record->is_fulfilled
            && $record->type === $type
            && ($type !== AssetRequestType::Perbaikan || $record->requestedAssetIds() !== []);
    }
}
