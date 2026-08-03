<?php

namespace App\Filament\Actions;

use App\Models\Asset;
use App\Services\CsaAssetAuditWorkbookExporter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use LogicException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

final class ExportCsaAuditFormatAction
{
    public static function make(string $name = 'exportCsaFormat'): Action
    {
        return Action::make($name)
            ->label('0. Export Format CSA')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->tooltip('Unduh saldo Shelf dalam format audit CSA. Isi kolom Fisik/Selisih, lalu Import.')
            ->visible(fn (): bool => auth()->user()?->can('import', Asset::class)
                || auth()->user()?->can('export', Asset::class))
            ->action(function (): ?BinaryFileResponse {
                try {
                    $path = app(CsaAssetAuditWorkbookExporter::class)->exportToPath();
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
