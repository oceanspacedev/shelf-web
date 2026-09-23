<?php

namespace Tests\Feature;

use App\Models\Asset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class GenerateMissingAssetQrsCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_backfill_command_creates_missing_qrs(): void
    {
        $asset = Asset::factory()->create();
        $asset->qr()->delete();
        $asset->unsetRelation('qr');

        $this->artisan('assets:generate-missing-qrs')
            ->assertSuccessful();

        $this->assertNotNull($asset->fresh()->qr);
    }
}
