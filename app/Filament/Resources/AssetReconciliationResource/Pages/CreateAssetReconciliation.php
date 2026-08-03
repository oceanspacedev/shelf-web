<?php

namespace App\Filament\Resources\AssetReconciliationResource\Pages;

use App\Filament\Actions\ExportCsaAuditFormatAction;
use App\Filament\Resources\AssetReconciliationResource;
use App\Models\AssetReconciliation;
use App\Services\AssetReconciliationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateAssetReconciliation extends CreateRecord
{
    protected static string $resource = AssetReconciliationResource::class;

    public function getTitle(): string
    {
        return '1. Import Audit CSA';
    }

    public function getSubheading(): ?string
    {
        return 'Unggah workbook hasil Export Format CSA yang sudah diisi Fisik/Selisih. Setelah simpan, sistem langsung membuat Laporan.';
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportCsaAuditFormatAction::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $storedPath = is_array($data['stored_path']) ? reset($data['stored_path']) : $data['stored_path'];
        $originalFilename = $data['original_filename'] ?? basename($storedPath);
        $originalFilename = is_array($originalFilename) ? reset($originalFilename) : $originalFilename;
        $absolutePath = Storage::disk('local')->path($storedPath);

        return [
            ...$data,
            'stored_path' => $storedPath,
            'source_system' => 'CSA',
            'original_filename' => $originalFilename,
            'file_sha256' => hash_file('sha256', $absolutePath),
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'imported_by' => auth()->id(),
        ];
    }

    protected function afterCreate(): void
    {
        try {
            app(AssetReconciliationService::class)->compare($this->record);

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
