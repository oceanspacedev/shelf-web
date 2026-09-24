<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetQr;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssetQrService
{
    public function createForAsset(Asset $asset): AssetQr
    {
        if ($asset->qr()->exists()) {
            throw new RuntimeException('Asset already has a QR code.');
        }

        return $asset->qr()->create([]);
    }

    public function ensureForAsset(Asset $asset): AssetQr
    {
        return $asset->qr()->first() ?? $this->createForAsset($asset);
    }

    public function regenerate(Asset $asset): AssetQr
    {
        return DB::transaction(function () use ($asset) {
            $asset->qr()->delete();
            $asset->unsetRelation('qr');

            return $this->createForAsset($asset);
        });
    }

    public function publicUrl(AssetQr $qr): string
    {
        return rtrim((string) config('app.url'), '/').'/qr/'.$qr->id;
    }

    public function png(AssetQr $qr, int $size = 300): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($this->publicUrl($qr))
            ->size($size)
            ->margin(10)
            ->build();

        return $result->getString();
    }
}
