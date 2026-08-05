<?php

namespace App\Filament\Actions;

use App\Models\Asset;
use App\Services\VehicleAssetAuditWorkbookExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ExportVehicleAuditFormatAction
{
    public static function make(string $name = 'exportVehicleAuditFormat'): Action
    {
        return Action::make($name)
            ->label('0. Export Format Audit Kendaraan')
            ->icon('heroicon-o-truck')
            ->color('gray')
            ->tooltip('Unduh aset MOBIL/MOTOR Shelf dalam format Monitoring Asset. Isi audit, lalu Import VEHICLE_AUDIT.')
            ->visible(fn (): bool => auth()->user()?->can('import', Asset::class)
                || auth()->user()?->can('export', Asset::class))
            ->action(function (): ?BinaryFileResponse {
                try {
                    $path = app(VehicleAssetAuditWorkbookExporter::class)->exportToPath();
                } catch (LogicException $exception) {
                    Notification::make()
                        ->title('Tidak ada data untuk diekspor')
                        ->body($exception->getMessage())
                        ->warning()
                        ->send();

                    return null;
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Export gagal')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return null;
                }

                return response()
                    ->download($path, basename($path))
                    ->deleteFileAfterSend();
            });
    }
}
