<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getActions(): array
    {
        return [
            Actions\Action::make('markSold')
                ->label('Tandai Dijual')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->visible(fn(Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
                    && $record->condition_status !== AssetCondition::Sold)
                ->modalHeading('Lengkapi audit penjualan aset')
                ->modalDescription('Isi data tujuan penjualan, nilai transaksi, dan dokumen pendukung sebelum status diubah menjadi "Dijual".')
                ->form($this->getSaleFormSchema())
                ->action(function (array $data): void {
                    /** @var Asset $asset */
                    $asset = $this->record;
                    $this->applySaleUpdate($asset, $data);

                    Notification::make()
                        ->title('Aset ditandai dijual')
                        ->body('Status aset berubah menjadi "Dijual" beserta data audit penjualannya.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('markDamaged')
                ->label('Tandai Rusak')
                ->icon('heroicon-o-wrench')
                ->color('warning')
                ->visible(fn(Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
                    && ! in_array($record->condition_status, [AssetCondition::Damaged, AssetCondition::Sold], true))
                ->form($this->getIncidentFormSchema())
                ->action(function (array $data): void {
                    /** @var Asset $asset */
                    $asset = $this->record;
                    $this->applyIncidentUpdate($asset, AssetCondition::Damaged, $data);

                    Notification::make()
                        ->title('Aset ditandai rusak')
                        ->body('Status aset berubah menjadi "Rusak" dan NBH menunggu tindak lanjut.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('markLost')
                ->label('Tandai Hilang')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn(Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
                    && ! in_array($record->condition_status, [AssetCondition::Lost, AssetCondition::Sold], true))
                ->form($this->getIncidentFormSchema())
                ->action(function (array $data): void {
                    /** @var Asset $asset */
                    $asset = $this->record;
                    $this->applyIncidentUpdate($asset, AssetCondition::Lost, $data);

                    Notification::make()
                        ->title('Aset ditandai hilang')
                        ->body('Status aset berubah menjadi "Hilang" dan NBH menunggu tindak lanjut.')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getIncidentFormSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('nbh_reported_at')
                ->label('Tanggal Insiden')
                ->native(false)
                ->helperText('Opsional, bisa diisi setelah audit.'),
            Forms\Components\Select::make('nbh_responsible_user_id')
                ->label('Penanggung Jawab')
                ->searchable()
                ->options(User::orderBy('name')->pluck('name', 'id'))
                ->helperText('Isi ketika penanggung jawab sudah ditetapkan.')
                ->placeholder('Belum ditentukan'),
            Forms\Components\FileUpload::make('audit_document_path')
                ->label('Dokumen Audit')
                ->directory('asset-audit')
                ->preserveFilenames()
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(4096)
                ->helperText('Unggah BAP ketika audit selesai.'),
            Forms\Components\Textarea::make('nbh_notes')
                ->label('Catatan')
                ->rows(3)
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    protected function getSaleFormSchema(): array
    {
        return [
            Forms\Components\DatePicker::make('sold_at')
                ->label('Tanggal Jual')
                ->native(false)
                ->default(now())
                ->required(),
            Forms\Components\TextInput::make('sold_to')
                ->label('Dijual Ke')
                ->maxLength(255)
                ->placeholder('Nama pembeli, vendor, atau tujuan penjualan')
                ->required(),
            Forms\Components\TextInput::make('sold_price')
                ->label('Harga Jual')
                ->numeric()
                ->prefix('Rp')
                ->required(),
            Forms\Components\FileUpload::make('sale_document_path')
                ->label('Dokumen Penjualan')
                ->directory('asset-sales')
                ->preserveFilenames()
                ->acceptedFileTypes(['application/pdf', 'image/*'])
                ->maxSize(4096)
                ->helperText('Unggah kuitansi, invoice, BA penjualan, atau bukti transfer.')
                ->required(),
            Forms\Components\Textarea::make('sale_notes')
                ->label('Catatan Penjualan')
                ->rows(3)
                ->placeholder('Nomor referensi, metode pembayaran, atau detail audit lainnya.')
                ->columnSpanFull(),
        ];
    }

    protected function applyIncidentUpdate(Asset $asset, AssetCondition $condition, array $data): void
    {
        $asset->condition_status = $condition;
        $asset->nbh_status = NbhStatus::Pending;
        $asset->nbh_reported_at = $data['nbh_reported_at'] ?? null;
        $asset->nbh_responsible_user_id = $data['nbh_responsible_user_id'] ?? null;

        if (! empty($data['audit_document_path'])) {
            $asset->audit_document_path = is_array($data['audit_document_path'])
                ? $data['audit_document_path'][0] ?? null
                : $data['audit_document_path'];
        }

        $asset->nbh_notes = $data['nbh_notes'] ?? null;
        $asset->save();
    }

    protected function applySaleUpdate(Asset $asset, array $data): void
    {
        $asset->condition_status = AssetCondition::Sold;
        $asset->sold_at = $data['sold_at'] ?? null;
        $asset->sold_to = $data['sold_to'] ?? null;
        $asset->sold_price = $data['sold_price'] ?? null;

        if (! empty($data['sale_document_path'])) {
            $asset->sale_document_path = is_array($data['sale_document_path'])
                ? $data['sale_document_path'][0] ?? null
                : $data['sale_document_path'];
        }

        $asset->sale_notes = $data['sale_notes'] ?? null;
        $asset->save();
    }
}
