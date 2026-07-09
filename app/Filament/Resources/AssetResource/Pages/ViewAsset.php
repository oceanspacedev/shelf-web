<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetTransferDetail;
use App\Models\User;
use App\Models\VehicleChecksheet;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    protected function getActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('markSold')
                    ->label('Tandai Dijual')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->visible(fn (Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
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
                    ->visible(fn (Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
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
                    ->visible(fn (Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
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
                Actions\Action::make('completeRepair')
                    ->label('Selesaikan Perbaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
                        && $record->condition_status === AssetCondition::Damaged
                        && $record->nbh_status === NbhStatus::Pending)
                    ->form(AssetResource::repairCompletionFormSchema())
                    ->slideOver()
                    ->modalWidth('md')
                    ->action(function (array $data): void {
                        /** @var Asset $asset */
                        $asset = $this->record;
                        $asset->completeRepairProcessing(auth()->user(), $data);
                        $this->record->refresh();

                        Notification::make()
                            ->title('Perbaikan selesai')
                            ->body('Aset sudah kembali operasional dan NBH ditutup.')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('repairValidation')
                    ->label('Perbaiki Validasi')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Asset $record): bool => auth()->user()?->hasAnyRole(['super_admin', 'general_affair'])
                        && ! $record->checkValidRecipient())
                    ->requiresConfirmation()
                    ->modalHeading('Perbaiki status validasi aset')
                    ->modalDescription('Aset akan disesuaikan dengan Asset Transfer Detail terbaru: pemegang aset, entitas penerima, dan status kondisi.')
                    ->modalSubmitActionLabel('Perbaiki')
                    ->action(function (): void {
                        /** @var Asset $asset */
                        $asset = $this->record;

                        if (! $asset->syncRecipientFromLatestTransferDetail()) {
                            Notification::make()
                                ->title('Validasi belum bisa diperbaiki')
                                ->body('Asset Transfer Detail terbaru tidak ditemukan atau data transfernya tidak lengkap.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $this->record->refresh();

                        Notification::make()
                            ->title('Validasi aset diperbaiki')
                            ->body('Pemegang aset, entitas penerima, dan status aset sudah disesuaikan dengan transfer terbaru.')
                            ->success()
                            ->send();
                    }),
                Actions\Action::make('mergeAsset')
                    ->label('Gabungkan Aset Duplikat')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->visible(fn (): bool => auth()->user()?->hasRole('super_admin') ?? false)
                    ->modalHeading('Gabungkan Aset Duplikat')
                    ->modalDescription('Pindahkan semua relasi dari aset sumber ke aset ini, lalu hapus aset sumber.')
                    ->modalSubmitActionLabel('Gabungkan')
                    ->modalWidth('xl')
                    ->form([
                        Forms\Components\Select::make('source_asset_id')
                            ->label('Aset Sumber (Duplikat)')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return Asset::where('id', '!=', $this->record->id)
                                    ->where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('id', 'like', "%{$search}%");
                                    })
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn (Asset $asset) => [
                                        $asset->id => "#{$asset->id} — {$asset->name}",
                                    ])
                                    ->toArray();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $asset = Asset::find($value);

                                return $asset ? "#{$asset->id} — {$asset->name}" : null;
                            })
                            ->required()
                            ->helperText('Cari berdasarkan ID atau nama aset.')
                            ->live()
                            ->afterStateUpdated(function (callable $set, $state) {
                                if ($state) {
                                    $allIds = AssetTransferDetail::where('asset_id', $state)->pluck('id')->toArray();
                                    $conflicts = $this->detectConflictingTransferDetails((int) $this->record->id, (int) $state);
                                    $conflictIds = $conflicts['source'] ?? [];
                                    $safeIds = array_values(array_diff($allIds, $conflictIds));
                                    $set('transfer_details_to_move', $safeIds);
                                } else {
                                    $set('transfer_details_to_move', []);
                                }
                            }),
                        Placeholder::make('source_preview')
                            ->label('Ringkasan Aset Sumber')
                            ->content(function (callable $get): string {
                                $sourceId = $get('source_asset_id');
                                if (! $sourceId) {
                                    return 'Pilih aset sumber untuk melihat ringkasan.';
                                }

                                $source = Asset::withCount(['attributes', 'assetTransferDetails', 'vehicleChecksheets'])
                                    ->find($sourceId);

                                if (! $source) {
                                    return 'Aset tidak ditemukan.';
                                }

                                return implode(' | ', array_filter([
                                    "Nama: {$source->name}",
                                    "Atribut: {$source->attributes_count}",
                                    "Transfer Detail: {$source->asset_transfer_details_count}",
                                    "Checksheet: {$source->vehicle_checksheets_count}",
                                ]));
                            })
                            ->visible(fn (callable $get): bool => filled($get('source_asset_id'))),
                        Forms\Components\CheckboxList::make('transfer_details_to_move')
                            ->label('Riwayat Transfer dari Aset Sumber')
                            ->options(function (callable $get): array {
                                $sourceId = $get('source_asset_id');
                                if (! $sourceId) {
                                    return [];
                                }

                                $conflicts = $this->detectConflictingTransferDetails((int) $this->record->id, (int) $sourceId);
                                $conflictIds = $conflicts['source'] ?? [];

                                return AssetTransferDetail::where('asset_id', $sourceId)
                                    ->with(['assetTransfer' => fn ($q) => $q->with('fromUser', 'toUser')])
                                    ->get()
                                    ->mapWithKeys(function (AssetTransferDetail $detail) use ($conflictIds) {
                                        $transfer = $detail->assetTransfer;
                                        if (! $transfer) {
                                            return [$detail->id => "⚠️ Detail #{$detail->id} (transfer tidak ditemukan)"];
                                        }
                                        $from = $transfer->fromUser?->name ?? 'N/A';
                                        $to = $transfer->toUser?->name ?? 'N/A';
                                        $date = $transfer->transfer_date
                                            ? Carbon::parse($transfer->transfer_date)->format('d M Y')
                                            : '-';

                                        $prefix = in_array($detail->id, $conflictIds) ? '⚠️ KONFLIK — ' : '✅ ';

                                        return [$detail->id => "{$prefix}{$transfer->letter_number} | {$from} → {$to} | {$date}"];
                                    })
                                    ->toArray();
                            })
                            ->helperText('Transfer bertanda ⚠️ KONFLIK otomatis tidak terpilih.')
                            ->columns(1)
                            ->visible(fn (callable $get): bool => filled($get('source_asset_id'))
                                && AssetTransferDetail::where('asset_id', $get('source_asset_id'))->exists()),
                        Placeholder::make('target_conflicts_preview')
                            ->label('Transfer Aset Target yang Akan Dihapus (Konflik)')
                            ->content(function (callable $get): string {
                                $sourceId = $get('source_asset_id');
                                if (! $sourceId) {
                                    return '';
                                }

                                $conflicts = $this->detectConflictingTransferDetails((int) $this->record->id, (int) $sourceId);
                                $targetConflictIds = $conflicts['target'] ?? [];

                                if (empty($targetConflictIds)) {
                                    return 'Tidak ada transfer detail dari aset target yang perlu dihapus.';
                                }

                                $details = AssetTransferDetail::whereIn('id', $targetConflictIds)
                                    ->with(['assetTransfer' => fn ($q) => $q->with('fromUser', 'toUser')])
                                    ->get()
                                    ->map(function (AssetTransferDetail $detail) {
                                        $transfer = $detail->assetTransfer;
                                        if (! $transfer) {
                                            return "• Detail #{$detail->id} (transfer tidak ditemukan)";
                                        }
                                        $from = $transfer->fromUser?->name ?? 'N/A';
                                        $to = $transfer->toUser?->name ?? 'N/A';
                                        $date = $transfer->transfer_date
                                            ? Carbon::parse($transfer->transfer_date)->format('d M Y')
                                            : '-';

                                        return "• {$transfer->letter_number} | {$from} → {$to} | {$date}";
                                    })
                                    ->implode("\n");

                                return "Transfer berikut tidak cocok dengan alur timeline baru dan akan dihapus:\n\n{$details}";
                            })
                            ->visible(function (callable $get): bool {
                                $sourceId = $get('source_asset_id');
                                if (! $sourceId) {
                                    return false;
                                }
                                $conflicts = $this->detectConflictingTransferDetails((int) $this->record->id, (int) $sourceId);

                                return ! empty($conflicts['target']);
                            }),
                    ])
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-exclamation-triangle')
                    ->modalIconColor('warning')
                    ->action(function (array $data): void {
                        $this->performMerge(
                            (int) $data['source_asset_id'],
                            $data['transfer_details_to_move'] ?? []
                        );
                    }),
            ])
                ->label('Pilihan')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button(),
            Actions\EditAction::make(),
        ];
    }

    /**
     * Deteksi transfer detail dari aset target dan sumber yang tidak cocok dengan alur transfer gabungan.
     *
     * Algoritma:
     * 1. Gabungkan semua transfer dari target dan sumber, urutkan berdasarkan tanggal
     * 2. Cari rantai terpanjang yang valid (to_user N = from_user N+1)
     * 3. Pisahkan transfer target dan sumber yang tidak masuk rantai optimal sebagai konflik/tidak valid.
     *
     * @return array{source: array<int>, target: array<int>} ID transfer detail target dan sumber yang konflik
     */
    protected function detectConflictingTransferDetails(int $targetAssetId, int $sourceAssetId): array
    {
        $loadTransfer = fn ($q) => $q->with('fromUser', 'toUser');

        $targetDetails = AssetTransferDetail::where('asset_id', $targetAssetId)
            ->with(['assetTransfer' => $loadTransfer])
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'origin' => 'target',
                'from_user_id' => $d->assetTransfer?->from_user_id,
                'to_user_id' => $d->assetTransfer?->to_user_id,
                'date' => $d->assetTransfer?->transfer_date,
            ]);

        $sourceDetails = AssetTransferDetail::where('asset_id', $sourceAssetId)
            ->with(['assetTransfer' => $loadTransfer])
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'origin' => 'source',
                'from_user_id' => $d->assetTransfer?->from_user_id,
                'to_user_id' => $d->assetTransfer?->to_user_id,
                'date' => $d->assetTransfer?->transfer_date,
            ]);

        $combined = $targetDetails->concat($sourceDetails)
            ->sortBy('date')
            ->values()
            ->toArray();

        if (empty($combined)) {
            return ['source' => [], 'target' => []];
        }

        // Cari rantai terpanjang yang valid menggunakan dynamic programming (LIS variant)
        // Rantai valid = to_user[j] == from_user[i] untuk setiap pasangan berurutan
        // Prioritas: panjang rantai (jumlah node terbanyak menang)
        $n = count($combined);
        $dp = array_fill(0, $n, 1);     // panjang rantai terpanjang ending di i
        $prev = array_fill(0, $n, -1);  // predecessor index

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $i; $j++) {
                // Rantai valid jika to_user[j] == from_user[i]
                if ($combined[$j]['to_user_id'] === $combined[$i]['from_user_id']) {
                    if ($dp[$i] < $dp[$j] + 1) {
                        $dp[$i] = $dp[$j] + 1;
                        $prev[$i] = $j;
                    }
                }
            }
        }

        // Temukan akhir rantai terpanjang
        $bestEnd = 0;
        for ($i = 1; $i < $n; $i++) {
            if ($dp[$i] > $dp[$bestEnd]) {
                $bestEnd = $i;
            }
        }

        // Traceback: kumpulkan ID yang masuk rantai optimal
        $chainIds = [];
        $idx = $bestEnd;
        while ($idx !== -1) {
            $chainIds[] = $combined[$idx]['id'];
            $idx = $prev[$idx];
        }

        // Target transfers yang TIDAK masuk rantai optimal = konflik target
        $allTargetIds = $targetDetails->pluck('id')->toArray();
        $targetConflictIds = array_values(array_diff($allTargetIds, $chainIds));

        // Source transfers yang TIDAK masuk rantai optimal = konflik source
        $allSourceIds = $sourceDetails->pluck('id')->toArray();
        $sourceConflictIds = array_values(array_diff($allSourceIds, $chainIds));

        return [
            'source' => $sourceConflictIds,
            'target' => $targetConflictIds,
        ];
    }

    protected function performMerge(int $sourceAssetId, array $transferDetailIdsToMove = []): void
    {
        /** @var Asset $target */
        $target = $this->record;

        $source = Asset::withCount(['attributes', 'assetTransferDetails', 'vehicleChecksheets'])
            ->findOrFail($sourceAssetId);

        if ($source->id === $target->id) {
            Notification::make()
                ->title('Gagal')
                ->body('Tidak bisa menggabungkan aset dengan dirinya sendiri.')
                ->danger()
                ->send();

            return;
        }

        DB::transaction(function () use ($source, $target, $transferDetailIdsToMove) {
            // 1. Pindahkan asset_attributes (skip jika custom_attribute_id sudah ada di target)
            $existingAttributeIds = AssetAttribute::where('asset_id', $target->id)
                ->pluck('custom_attribute_id')
                ->toArray();

            AssetAttribute::where('asset_id', $source->id)
                ->whereNotIn('custom_attribute_id', $existingAttributeIds)
                ->update(['asset_id' => $target->id]);

            // Hapus atribut sumber yang duplikat (sudah ada di target)
            AssetAttribute::where('asset_id', $source->id)->delete();

            // 2. Deteksi dan hapus transfer detail target yang tidak valid
            $conflicts = $this->detectConflictingTransferDetails((int) $target->id, (int) $source->id);
            $targetConflictIds = $conflicts['target'] ?? [];
            if (! empty($targetConflictIds)) {
                AssetTransferDetail::whereIn('id', $targetConflictIds)->delete();
            }

            // 3. Pindahkan asset_transfer_details yang dipilih (non-konflik)
            if (! empty($transferDetailIdsToMove)) {
                AssetTransferDetail::where('asset_id', $source->id)
                    ->whereIn('id', $transferDetailIdsToMove)
                    ->update(['asset_id' => $target->id]);
            }

            // Hapus transfer details yang konflik (tidak dipilih)
            AssetTransferDetail::where('asset_id', $source->id)->delete();

            // 4. Pindahkan vehicle_checksheets
            VehicleChecksheet::where('asset_id', $source->id)
                ->update(['asset_id' => $target->id]);

            // 5. Hapus aset sumber
            $source->delete();
        });

        Notification::make()
            ->title('Aset berhasil digabungkan')
            ->body("Semua relasi dari aset #{$sourceAssetId} ({$source->name}) telah dipindahkan ke aset ini dan aset sumber telah dihapus.")
            ->success()
            ->send();
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
