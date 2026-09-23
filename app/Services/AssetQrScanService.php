<?php

namespace App\Services;

use App\Models\AssetQr;
use App\Models\AssetQrScan;
use App\Models\User;

class AssetQrScanService
{
    public function record(
        AssetQr $qr,
        ?User $user,
        ?string $userAgent,
        ?float $lat = null,
        ?float $lng = null,
    ): AssetQrScan {
        return $qr->scans()->create([
            'user_id' => $user?->id,
            'user_agent' => $userAgent,
            'latitude' => $lat,
            'longitude' => $lng,
        ]);
    }

    public function attachLocation(AssetQrScan $scan, float $lat, float $lng): AssetQrScan
    {
        $scan->latitude = $lat;
        $scan->longitude = $lng;
        $scan->save();

        return $scan->refresh();
    }
}
