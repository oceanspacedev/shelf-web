<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->legacyTables() as $currentName => $archiveName) {
            if (Schema::hasTable($currentName) && ! Schema::hasTable($archiveName)) {
                Schema::rename($currentName, $archiveName);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_reverse($this->legacyTables()) as $currentName => $archiveName) {
            if (Schema::hasTable($archiveName) && ! Schema::hasTable($currentName)) {
                Schema::rename($archiveName, $currentName);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function legacyTables(): array
    {
        return [
            'request_approvals' => 'legacy_request_approvals',
            'approval_levels' => 'legacy_approval_levels',
            'public_asset_requests' => 'legacy_public_asset_requests',
        ];
    }
};
