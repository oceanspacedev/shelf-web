<?php

namespace App\Support;

use Apriansyahrs\MekayaTheme\Mekaya;

class MekayaAssets extends Mekaya
{
    /**
     * Vite manifest keys always use forward slashes. On Windows, the package
     * builds this path from dirname(), which leaves backslashes in the vendor
     * segment and misses the manifest entry.
     */
    public function viteInput(string $relative): string
    {
        return str_replace('\\', '/', parent::viteInput($relative));
    }
}
