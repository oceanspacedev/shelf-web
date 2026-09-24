<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Services\AssetQrLabelHistoryService;
use App\Services\AssetQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AssetQrLabelPrintController extends Controller
{
    public function show(
        Asset $asset,
        AssetQrService $qrService,
        AssetQrLabelHistoryService $historyService,
    ): View {
        $this->authorize('view', $asset);

        $qrService->ensureForAsset($asset);
        $asset->loadMissing(['qr', 'assetLocation']);

        $historyService->record(
            request()->user(),
            AssetQrLabelHistory::ACTION_PRINT,
            [$asset],
        );

        return view('qr.print-labels', [
            'assets' => collect([$asset]),
            'qrService' => $qrService,
            'autoPrint' => true,
        ]);
    }

    public function bulk(
        Request $request,
        AssetQrService $qrService,
        AssetQrLabelHistoryService $historyService,
    ): View {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        abort_if($ids->isEmpty(), 404);

        $assetsById = Asset::query()
            ->with(['qr', 'assetLocation'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Asset> $assets */
        $assets = $ids
            ->map(fn (int $id) => $assetsById->get($id))
            ->filter()
            ->values();

        abort_if($assets->isEmpty(), 404);

        foreach ($assets as $asset) {
            $this->authorize('view', $asset);
            $qrService->ensureForAsset($asset);
        }

        $assets->each->loadMissing(['qr', 'assetLocation']);

        $historyService->record(
            $request->user(),
            AssetQrLabelHistory::ACTION_PRINT,
            $assets,
        );

        return view('qr.print-labels', [
            'assets' => $assets,
            'qrService' => $qrService,
            'autoPrint' => true,
        ]);
    }
}
