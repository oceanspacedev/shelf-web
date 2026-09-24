<?php

namespace App\Filament\Resources\AssetReconciliationResource\Pages;

use App\Filament\Actions\ExportCsaAuditFormatAction;
use App\Filament\Actions\ExportVehicleAuditFormatAction;
use App\Filament\Resources\AssetReconciliationResource;
use App\Models\AssetReconciliation;
use App\Services\AssetReconciliationService;
use App\Services\VehicleAssetAuditWorkbookParser;
use App\Services\VehicleAssetReconciliationService;
use App\Support\StoredFile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;

class CreateAssetReconciliation extends CreateRecord
{
    protected static string $resource = AssetReconciliationResource::class;

    public function getTitle(): string
    {
        return '1. Import Audit';
    }

    public function getSubheading(): ?string
    {
        return 'Pilih CSA atau Kendaraan, unggah workbook audit. Setelah simpan, sistem langsung membuat Laporan (tanpa mengubah Shelf).';
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportCsaAuditFormatAction::make(),
            ExportVehicleAuditFormatAction::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storedPath = is_array($data['stored_path']) ? reset($data['stored_path']) : $data['stored_path'];
        $originalFilename = $data['original_filename'] ?? basename($storedPath);
        $originalFilename = is_array($originalFilename) ? reset($originalFilename) : $originalFilename;
        $diskName = config('filesystems.default');
        $checksum = StoredFile::withLocalPath($diskName, $storedPath, fn (string $path) => hash_file('sha256', $path));
        $sourceSystem = $data['source_system'] ?? 'CSA';
        $sourceSheet = $data['source_sheet'] ?? null;

        if ($sourceSystem === 'VEHICLE_AUDIT' && (blank($sourceSheet) || $sourceSheet === 'ASET')) {
            $sourceSheet = VehicleAssetAuditWorkbookParser::DEFAULT_SHEET;
        }

        if ($sourceSystem !== 'VEHICLE_AUDIT' && blank($sourceSheet)) {
            $sourceSheet = 'ASET';
        }

        return [
            ...$data,
            'stored_path' => $storedPath,
            'stored_disk' => $diskName,
            'source_system' => $sourceSystem,
            'source_sheet' => $sourceSheet,
            'original_filename' => $originalFilename,
            'file_sha256' => $checksum,
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'imported_by' => auth()->id(),
        ];
    }

    protected function afterCreate(): void
    {
        try {
            if ($this->record->source_system === 'VEHICLE_AUDIT') {
                app(VehicleAssetReconciliationService::class)->compare($this->record);
            } else {
                app(AssetReconciliationService::class)->compare($this->record);
            }

            Notification::make()
                ->title('Import selesai — laporan siap')
                ->body('Gap Shelf dengan hasil audit sudah dihitung. Tinjau laporan, lalu Apply hanya jika aman.')
                ->success()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            $this->record->refresh();

            Notification::make()
                ->title('Workbook gagal diproses')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null;
    }
}
