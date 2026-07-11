<?php

namespace Tests\Feature;

use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource;
use App\Models\AssetRequest;
use App\Models\AssetRequestItem;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Mockery;
use Tests\TestCase;

class AssetRequestResourceTableActionTest extends TestCase
{
    public function test_approved_pengadaan_request_has_create_asset_row_action(): void
    {
        $table = AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));

        $this->assertTrue($table->hasAction('fulfillPengadaan'));

        $action = $table->getFlatActions()['fulfillPengadaan'];

        $approvedPengadaan = new AssetRequest([
            'type' => AssetRequestType::Pengadaan,
            'status' => RequestStatus::Approved,
            'fulfilled_at' => null,
        ]);

        $this->assertTrue($action->record($approvedPengadaan)->isVisible());
    }

    public function test_create_asset_action_redirects_to_asset_create_with_request_context(): void
    {
        $table = AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));

        $approvedPengadaan = new class(['item_name' => 'Laptop Operasional', 'type' => AssetRequestType::Pengadaan, 'status' => RequestStatus::Approved, 'qty' => 1, 'fulfilled_at' => null]) extends AssetRequest
        {
            public function nextUnfulfilledPengadaanItem(): ?AssetRequestItem
            {
                return null;
            }
        };
        $approvedPengadaan->id = 123;

        $url = $table->getFlatActions()['fulfillPengadaan']
            ->record($approvedPengadaan)
            ->getUrl();

        $this->assertStringContainsString('/assets/create', $url);
        $this->assertStringContainsString('asset_request_id=123', $url);
    }

    public function test_approved_penarikan_request_has_create_return_transfer_row_action(): void
    {
        $table = AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));

        $this->assertTrue($table->hasAction('fulfillPenarikan'));

        $action = $table->getFlatActions()['fulfillPenarikan'];

        $approvedPenarikan = new AssetRequest([
            'type' => AssetRequestType::Penarikan,
            'status' => RequestStatus::Approved,
            'fulfilled_at' => null,
        ]);
        $approvedPenarikan->id = 456;

        $this->assertTrue($action->record($approvedPenarikan)->isVisible());

        $url = $action->record($approvedPenarikan)->getUrl();

        $this->assertStringContainsString('/asset-transfers/create', $url);
        $this->assertStringContainsString('asset_request_id=456', $url);
    }

    public function test_approved_perbaikan_request_has_repair_follow_up_row_action(): void
    {
        $table = AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));

        $this->assertTrue($table->hasAction('fulfillPerbaikan'));

        $action = $table->getFlatActions()['fulfillPerbaikan'];

        $approvedPerbaikan = new AssetRequest([
            'type' => AssetRequestType::Perbaikan,
            'status' => RequestStatus::Approved,
            'fulfilled_at' => null,
            'asset_id' => 789,
        ]);

        $this->assertTrue($action->record($approvedPerbaikan)->isVisible());

        // Aksi tidak boleh tampil untuk perbaikan tanpa aset terkait, karena
        // fulfillPerbaikan() melempar RuntimeException bila requestedAssets kosong.
        $approvedPerbaikanWithoutAsset = new AssetRequest([
            'type' => AssetRequestType::Perbaikan,
            'status' => RequestStatus::Approved,
            'fulfilled_at' => null,
            'asset_id' => null,
        ]);

        $this->assertFalse($action->record($approvedPerbaikanWithoutAsset)->isVisible());
    }

    public function test_asset_request_table_has_resend_notification_row_actions(): void
    {
        $table = AssetRequestResource::table(Table::make(Mockery::mock(HasTable::class)));

        $this->assertTrue($table->hasAction('resendApprovalNotification'));
        $this->assertTrue($table->hasAction('resendRequesterNotification'));
    }
}
