<?php

namespace Tests\Feature;

use App\Enums\AssetTransferDocumentType;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * PDF BA: akun GA bersama (config/asset-transfer.php) mewakili departemen,
 * jadi nama dan jabatannya dikosongkan untuk diisi tangan; staf GA perorangan
 * tetap tercetak namanya.
 */
class AssetTransferPdfTest extends TestCase
{
    use DatabaseTransactions;

    public function test_shared_general_affair_account_is_left_blank_for_a_handwritten_name(): void
    {
        config(['asset-transfer.shared_general_affair_usernames' => ['__atp_shared__']]);

        $entity = BusinessEntity::create(['name' => '__atp_entity__']);
        $sharedAccount = User::factory()->create(['name' => '__atp_akun_ga_bersama__', 'username' => '__ATP_SHARED__']);
        $staff = User::factory()->create(['name' => '__atp_staf_ga__']);
        $holder = User::factory()->create(['name' => '__atp_pemegang__']);

        $this->assertTrue($sharedAccount->isSharedAccount(), 'Username dicocokkan tanpa membedakan huruf besar/kecil.');
        $this->assertFalse($staff->isSharedAccount());

        $html = $this->render($this->transfer($entity, $holder, $sharedAccount, AssetTransferDocumentType::PengembalianBarang));
        $this->assertStringNotContainsString('__atp_akun_ga_bersama__', $html);
        $this->assertStringContainsString('__atp_pemegang__', $html);

        $html = $this->render($this->transfer($entity, $staff, $holder, AssetTransferDocumentType::SerahTerima));
        $this->assertStringContainsString('__atp_staf_ga__', $html);
        $this->assertStringContainsString('__atp_pemegang__', $html);
    }

    private function transfer(BusinessEntity $entity, User $from, User $to, AssetTransferDocumentType $type): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'document_type' => $type,
            'letter_number' => '__ATP/'.uniqid().'__',
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ]);
    }

    private function render(AssetTransfer $transfer): string
    {
        return view('pdf.asset-transfer', [
            'assetTransfer' => $transfer->load('fromUser.jobTitle', 'toUser.jobTitle', 'details'),
            'headerImage' => '',
        ])->render();
    }
}
