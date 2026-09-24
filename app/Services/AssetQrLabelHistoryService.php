<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetQrLabelHistoryService
{
    /**
     * Satu sesi print/download = satu baris history + file PDF label QR.
     *
     * @param  iterable<Asset>|Collection<int, Asset>  $assets
     */
    public function record(?User $user, string $action, iterable $assets): ?AssetQrLabelHistory
    {
        $collection = collect($assets)->filter()->values();

        if ($collection->isEmpty()) {
            return null;
        }

        $qrService = app(AssetQrService::class);

        foreach ($collection as $asset) {
            $qrService->ensureForAsset($asset);
            $asset->loadMissing(['qr', 'assetLocation']);
        }

        $names = $collection
            ->map(fn (Asset $asset) => $asset->name)
            ->filter()
            ->values();

        $pdfBinary = Pdf::loadView('pdf.asset-qr-labels', [
            'assets' => $collection,
            'qrService' => $qrService,
        ])->output();

        $timestamp = now()->format('Ymd_His');
        $count = $collection->count();
        $fileName = $count === 1
            ? 'asset-qr-'.$collection->first()->id.'-'.$timestamp.'.pdf'
            : 'asset-qr-labels-'.$count.'-'.$timestamp.'.pdf';
        $filePath = 'asset-qr-labels/'.now()->format('Y/m').'/'.$fileName;

        Storage::disk('local')->put($filePath, $pdfBinary);

        return AssetQrLabelHistory::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'asset_ids' => $collection->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'asset_count' => $count,
            'asset_summary' => $names->implode(', '),
            'file_path' => $filePath,
            'file_name' => $fileName,
        ]);
    }

    public function download(AssetQrLabelHistory $history): StreamedResponse
    {
        abort_unless(
            filled($history->file_path) && Storage::disk('local')->exists($history->file_path),
            404
        );

        return Storage::disk('local')->download(
            $history->file_path,
            $history->file_name ?: basename($history->file_path),
            ['Content-Type' => 'application/pdf']
        );
    }
}
