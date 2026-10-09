<?php

namespace Tests\Feature;

use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource\Pages\ListAssetTransfers;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssetTransferFilterTest extends TestCase
{
    use DatabaseTransactions;

    private const TRANSFER_PERMISSIONS = [
        'view_any_asset::transfer',
        'view_asset::transfer',
    ];

    private BusinessEntity $entityA;
    private BusinessEntity $entityB;
    private BusinessEntity $entityC;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (self::TRANSFER_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->entityA = BusinessEntity::create(['name' => '__atflt_entity_a__', 'format' => 'A/']);
        $this->entityB = BusinessEntity::create(['name' => '__atflt_entity_b__', 'format' => 'B/']);
        $this->entityC = BusinessEntity::create(['name' => '__atflt_entity_c__', 'format' => 'C/']);

        $this->user = User::factory()->create([
            'access_all_business_entities' => true,
        ]);
        $this->user->givePermissionTo(self::TRANSFER_PERMISSIONS);
    }

    public function test_business_entity_filter_allows_multiple_selection(): void
    {
        $transferA = $this->createTransfer($this->entityA, '2026-06-01', 'BA/FLT/01');
        $transferB = $this->createTransfer($this->entityB, '2026-06-02', 'BA/FLT/02');
        $transferC = $this->createTransfer($this->entityC, '2026-06-03', 'BA/FLT/03');

        $this->actingAs($this->user);

        Livewire::test(ListAssetTransfers::class)
            ->filterTable('businessEntity', [$this->entityA->id, $this->entityB->id])
            ->assertCanSeeTableRecords([$transferA, $transferB])
            ->assertCanNotSeeTableRecords([$transferC]);
    }

    public function test_transfer_date_filter_by_period_range(): void
    {
        $transferEarly = $this->createTransfer($this->entityA, '2026-05-10', 'BA/FLT/10');
        $transferMid = $this->createTransfer($this->entityA, '2026-05-20', 'BA/FLT/20');
        $transferLate = $this->createTransfer($this->entityA, '2026-05-30', 'BA/FLT/30');

        $this->actingAs($this->user);

        // Filter range: from 2026-05-15 until 2026-05-25
        Livewire::test(ListAssetTransfers::class)
            ->filterTable('transfer_date', [
                'from' => '2026-05-15',
                'until' => '2026-05-25',
            ])
            ->assertCanSeeTableRecords([$transferMid])
            ->assertCanNotSeeTableRecords([$transferEarly, $transferLate]);

        // Filter from only: >= 2026-05-20
        Livewire::test(ListAssetTransfers::class)
            ->filterTable('transfer_date', [
                'from' => '2026-05-20',
            ])
            ->assertCanSeeTableRecords([$transferMid, $transferLate])
            ->assertCanNotSeeTableRecords([$transferEarly]);

        // Filter until only: <= 2026-05-20
        Livewire::test(ListAssetTransfers::class)
            ->filterTable('transfer_date', [
                'until' => '2026-05-20',
            ])
            ->assertCanSeeTableRecords([$transferEarly, $transferMid])
            ->assertCanNotSeeTableRecords([$transferLate]);
    }

    private function createTransfer(BusinessEntity $entity, string $transferDate, string $letterNumber): AssetTransfer
    {
        $otherUser = User::factory()->create();

        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'from_user_id' => $this->user->id,
            'to_user_id' => $otherUser->id,
            'document_type' => AssetTransferDocumentType::PengalihanBarang->value,
            'letter_number' => $letterNumber,
            'transfer_date' => $transferDate,
        ]);
    }
}
