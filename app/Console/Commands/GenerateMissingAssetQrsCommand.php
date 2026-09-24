<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\AssetQrService;
use Illuminate\Console\Command;

class GenerateMissingAssetQrsCommand extends Command
{
    protected $signature = 'assets:generate-missing-qrs';

    protected $description = 'Create QR codes for assets that do not have one yet';

    public function handle(AssetQrService $qrService): int
    {
        $query = Asset::query()->whereDoesntHave('qr');
        $count = 0;

        $query->orderBy('id')->chunkById(100, function ($assets) use ($qrService, &$count) {
            foreach ($assets as $asset) {
                $qrService->ensureForAsset($asset);
                $count++;
            }
        });

        $this->info("Generated {$count} QR code(s).");

        return self::SUCCESS;
    }
}
