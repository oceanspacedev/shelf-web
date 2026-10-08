<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetResource\Pages\ViewAsset;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Merge aset menghapus detail BA target yang tidak masuk rantai BA terpanjang.
 * Sejak stok tidak punya pemegang, staf GA yang menerima pengembalian boleh
 * berbeda dengan staf GA yang menyerahkan aset itu lagi; rantainya tetap utuh.
 */
class AssetMergeTransferChainTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = BusinessEntity::create(['name' => '__amc_entity__']);
    }

    public function test_return_to_stock_then_hand_over_by_another_general_affair_staff_is_one_chain(): void
    {
        $gaX = $this->user('__amc_ga_x__');
        $gaY = $this->user('__amc_ga_y__');
        $andi = $this->user('__amc_andi__');
        $budi = $this->user('__amc_budi__');
        $citra = $this->user('__amc_citra__');

        $source = $this->asset('__amc_source__');
        $target = $this->asset('__amc_target__');

        // Riwayat lama di aset sumber: diserahkan lalu dikembalikan ke stok lewat staf GA X.
        $this->detail($source, AssetTransferDocumentType::SerahTerima, $gaX, $andi, '2026-01-01');
        $this->detail($source, AssetTransferDocumentType::PengembalianBarang, $andi, $gaX, '2026-02-01');
        // Riwayat baru di aset target: staf GA Y mengeluarkan aset dari stok lalu dialihkan.
        $targetHandOver = $this->detail($target, AssetTransferDocumentType::SerahTerima, $gaY, $budi, '2026-03-01');
        $targetTransfer = $this->detail($target, AssetTransferDocumentType::PengalihanBarang, $budi, $citra, '2026-04-01');

        $conflicts = $this->conflicts($target, $source);

        $this->assertSame([], $conflicts['target'], 'Detail BA target tidak boleh dianggap konflik lalu dihapus.');
        $this->assertSame([], $conflicts['source']);
        $this->assertDatabaseHas('asset_transfer_details', ['id' => $targetHandOver->id]);
        $this->assertDatabaseHas('asset_transfer_details', ['id' => $targetTransfer->id]);
    }

    public function test_unrelated_holders_still_break_the_chain(): void
    {
        $gaX = $this->user('__amc_ga_x__');
        $andi = $this->user('__amc_andi__');
        $budi = $this->user('__amc_budi__');
        $citra = $this->user('__amc_citra__');

        $source = $this->asset('__amc_source__');
        $target = $this->asset('__amc_target__');

        $this->detail($source, AssetTransferDocumentType::SerahTerima, $gaX, $andi, '2026-01-01');
        $this->detail($source, AssetTransferDocumentType::PengalihanBarang, $andi, $budi, '2026-02-01');
        // Dialihkan oleh orang yang tidak sedang memegang aset: bukan kelanjutan rantai.
        $orphan = $this->detail($target, AssetTransferDocumentType::PengalihanBarang, $citra, $andi, '2026-03-01');

        $this->assertSame([$orphan->id], $this->conflicts($target, $source)['target']);
    }

    /**
     * @return array{source: array<int>, target: array<int>}
     */
    private function conflicts(Asset $target, Asset $source): array
    {
        $method = new ReflectionMethod(ViewAsset::class, 'detectConflictingTransferDetails');

        return $method->invoke(new ViewAsset, (int) $target->id, (int) $source->id);
    }

    private function detail(Asset $asset, AssetTransferDocumentType $type, User $from, User $to, string $date): AssetTransferDetail
    {
        $transfer = AssetTransfer::create([
            'business_entity_id' => $this->entity->id,
            'document_type' => $type,
            'letter_number' => '__AMC/'.uniqid().'__',
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => $date,
        ]);

        return AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);
    }

    private function user(string $name): User
    {
        return User::factory()->create(['name' => $name, 'business_entity_id' => $this->entity->id]);
    }

    private function asset(string $name): Asset
    {
        return Asset::create([
            'name' => $name,
            'business_entity_id' => $this->entity->id,
            'condition_status' => AssetCondition::Available,
        ]);
    }
}
