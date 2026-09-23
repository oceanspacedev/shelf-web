<?php

namespace App\Http\Controllers;

use App\Models\AssetQr;
use App\Models\AssetQrScan;
use App\Services\AssetQrScanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicAssetQrController extends Controller
{
    public function show(Request $request, AssetQr $qr, AssetQrScanService $scanService): View|Response
    {
        $qr->loadMissing([
            'asset.assetLocation',
            'asset.businessEntity',
            'asset.category',
            'asset.brand',
            'asset.recipient',
            'asset.attributes.customAttribute',
        ]);

        $asset = $qr->asset;
        if (! $asset) {
            return response()->view('qr.invalid', [], 404);
        }

        $scan = $scanService->record(
            $qr,
            $request->user(),
            $request->userAgent(),
        );

        $request->session()->put('asset_qr_scan_id_'.$qr->id, $scan->id);

        return view('qr.show', [
            'qr' => $qr,
            'asset' => $asset,
            'scan' => $scan,
        ]);
    }

    public function storeLocation(Request $request, AssetQr $qr, AssetQrScanService $scanService): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $scanId = $request->session()->get('asset_qr_scan_id_'.$qr->id);
        $scan = $scanId
            ? AssetQrScan::query()->where('asset_qr_id', $qr->id)->find($scanId)
            : null;

        if (! $scan) {
            $scan = $scanService->record($qr, $request->user(), $request->userAgent());
        }

        $scanService->attachLocation($scan, (float) $data['latitude'], (float) $data['longitude']);

        return response()->json(['ok' => true]);
    }
}
