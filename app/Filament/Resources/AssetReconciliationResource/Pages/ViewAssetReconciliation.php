<?php

namespace App\Filament\Resources\AssetReconciliationResource\Pages;

use App\Filament\Resources\AssetReconciliationResource;
use App\Models\AssetReconciliation;
use App\Models\BusinessEntity;
use App\Services\AssetReconciliationService;
use App\Services\VehicleAssetReconciliationService;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewAssetReconciliation extends ViewRecord
{
    protected static string $resource = AssetReconciliationResource::class;

    public function getTitle(): string
    {
        return '2. Laporan Hasil Banding';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        if ($record->blocked_rows > 0) {
            return "Ada {$record->blocked_rows} baris Terblokir. Selesaikan identitas/mapping dulu — Apply belum bisa dijalankan.";
        }

        if ($record->status === AssetReconciliation::STATUS_COMPARED && $record->gap_rows > 0) {
            return "Ada {$record->gap_rows} Gap siap ditinjau. Setelah aman, jalankan 3. Terapkan Koreksi.";
        }

        if ($record->status === AssetReconciliation::STATUS_APPLIED) {
            return 'Koreksi sudah diterapkan. Gunakan Compare Ulang untuk verifikasi Inline.';
        }

        if ($record->status === AssetReconciliation::STATUS_ALIGNED) {
            return 'Semua baris sudah Inline dengan Shelf.';
        }

        if ($record->status === AssetReconciliation::STATUS_FAILED) {
            return 'Import gagal diproses. Periksa penyebab di ringkasan, perbaiki workbook, lalu Import ulang.';
        }

        return 'Tinjau Inline, Gap, dan Blocked sebelum Apply.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('3. Terapkan Koreksi')
                ->icon('heroicon-o-check-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Apply: terapkan hasil laporan ke data Shelf?')
                ->modalDescription(fn (): string => $this->isVehicleAudit()
                    ? 'Langkah terakhir. Sold/duplikat/create/enrich diterapkan secara atomik. Aset tidak dihapus; duplikat dinonaktifkan dari inventori.'
                    : 'Langkah terakhir setelah Import dan Laporan. Kuantitas, lokasi, dan identitas item diselaraskan secara atomik. Aset target 0 tidak dihapus, tetapi dinonaktifkan dari inventori.')
                ->visible(fn (): bool => in_array($this->record->status, [
                    AssetReconciliation::STATUS_COMPARED,
                    AssetReconciliation::STATUS_ALIGNED,
                ], true) && $this->record->gap_rows > 0)
                ->disabled(fn (): bool => $this->record->blocked_rows > 0 || $this->record->applied_at !== null)
                ->tooltip(function (): ?string {
                    if ($this->record->blocked_rows > 0) {
                        return "Belum bisa Apply: {$this->record->blocked_rows} baris Terblokir harus diselesaikan dulu.";
                    }

                    return null;
                })
                ->action(function (): void {
                    try {
                        if ($this->isVehicleAudit()) {
                            app(VehicleAssetReconciliationService::class)->apply($this->record);
                        } else {
                            app(AssetReconciliationService::class)->apply($this->record);
                        }
                        $this->record->refresh();

                        Notification::make()
                            ->title('Koreksi berhasil diterapkan')
                            ->body('Gunakan Compare Ulang untuk memverifikasi bahwa data sudah inline.')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Apply gagal')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
            Action::make('recompare')
                ->label('Compare Ulang')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->modalHeading('Compare ulang dengan mapping badan usaha')
                ->modalDescription(fn (): string => $this->isVehicleAudit()
                    ? 'Marker ACC/STNK dari workbook dipakai lebih dulu. Pilih default untuk baris tanpa marker, dan isi override hanya jika suatu keberadaan perlu badan usaha berbeda.'
                    : 'Marker resmi dari workbook digunakan lebih dulu. Pilih default untuk baris tanpa marker dan isi override hanya jika suatu Gudang berbeda.')
                ->schema([
                    Select::make('business_entity_id')
                        ->label('Badan Usaha Default')
                        ->options(fn (): array => BusinessEntity::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?int => $this->record->business_entity_id)
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Pilih badan usaha resmi')
                        ->helperText(fn (): string => $this->isVehicleAudit()
                            ? 'Dipakai hanya untuk baris tanpa marker ACC/STNK yang resolve dan tanpa override keberadaan.'
                            : 'Dipakai hanya untuk baris yang tidak memiliki marker badan usaha dari CSA dan tidak memiliki override Gudang.'),
                    Repeater::make('location_mappings')
                        ->label(fn (): string => $this->isVehicleAudit()
                            ? 'Override Badan Usaha per Keberadaan'
                            : 'Override Badan Usaha per Gudang')
                        ->schema([
                            TextInput::make('external_location_code')
                                ->label(fn (): string => $this->isVehicleAudit() ? 'Keberadaan' : 'Gudang')
                                ->disabled()
                                ->dehydrated(),
                            TextInput::make('external_business_entity_code')
                                ->label(fn (): string => $this->isVehicleAudit() ? 'Marker ACC' : 'Marker CSA')
                                ->disabled()
                                ->placeholder('-'),
                            Select::make('business_entity_id')
                                ->label('Override')
                                ->options(fn (): array => BusinessEntity::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->placeholder('Gunakan marker/default'),
                        ])
                        ->default(fn (): array => $this->locationMappingDefaults())
                        ->columns(3)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => in_array($this->record->status, [
                    AssetReconciliation::STATUS_APPLIED,
                    AssetReconciliation::STATUS_ALIGNED,
                    AssetReconciliation::STATUS_COMPARED,
                ], true))
                ->action(function (array $data): void {
                    try {
                        $mappings = collect($data['location_mappings'] ?? [])
                            ->filter(fn (array $mapping): bool => filled($mapping['business_entity_id'] ?? null))
                            ->mapWithKeys(function (array $mapping): array {
                                $key = Normalizer::key($mapping['external_location_code'] ?? null)
                                    ?? (string) ($mapping['external_location_code'] ?? '');

                                return [$key => (int) $mapping['business_entity_id']];
                            })
                            ->all();

                        $service = $this->isVehicleAudit()
                            ? app(VehicleAssetReconciliationService::class)
                            : app(AssetReconciliationService::class);

                        $followUp = $service->recompare(
                            $this->record,
                            auth()->id(),
                            (int) $data['business_entity_id'],
                            $mappings,
                        );

                        $this->redirect(AssetReconciliationResource::getUrl('view', ['record' => $followUp]));
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Compare ulang gagal')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function locationMappingDefaults(): array
    {
        $overrides = $this->record->business_entity_mappings ?? [];

        return $this->record->items()
            ->orderBy('external_location_code')
            ->get(['external_location_code', 'external_business_entity_code'])
            ->unique('external_location_code')
            ->map(function ($item) use ($overrides): array {
                $raw = $item->external_location_code;
                $normalized = Normalizer::key($raw) ?? $raw;

                return [
                    'external_location_code' => $raw,
                    'external_business_entity_code' => $item->external_business_entity_code,
                    'business_entity_id' => $overrides[$normalized] ?? $overrides[$raw] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function isVehicleAudit(): bool
    {
        return ($this->record->source_system ?? null) === 'VEHICLE_AUDIT';
    }
}
