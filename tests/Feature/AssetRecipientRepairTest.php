<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetRecipientRepairTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');

        $this->createSchema();
    }

    public function test_asset_can_sync_recipient_from_latest_transfer_detail(): void
    {
        $oldEntity = BusinessEntity::create(['name' => 'CV Lama']);
        $latestEntity = BusinessEntity::create(['name' => 'CV Terbaru']);
        $fromUser = User::create(['name' => 'Pengirim']);
        $wrongRecipient = User::create(['name' => 'Penerima Lama']);
        $latestRecipient = User::create(['name' => 'Penerima Terbaru']);

        $asset = Asset::create([
            'name' => 'Laptop Operasional',
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'is_available' => true,
            'recipient_id' => $wrongRecipient->id,
            'recipient_business_entity_id' => $oldEntity->id,
        ]);

        $oldTransfer = AssetTransfer::create([
            'business_entity_id' => $oldEntity->id,
            'letter_number' => 'BAST-OLD',
            'from_user_id' => $fromUser->id,
            'to_user_id' => $wrongRecipient->id,
            'transfer_date' => now()->subDay(),
        ]);
        $latestTransfer = AssetTransfer::create([
            'business_entity_id' => $latestEntity->id,
            'letter_number' => 'BAST-NEW',
            'from_user_id' => $wrongRecipient->id,
            'to_user_id' => $latestRecipient->id,
            'transfer_date' => now(),
        ]);

        AssetTransferDetail::create([
            'asset_transfer_id' => $oldTransfer->id,
            'asset_id' => $asset->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
        AssetTransferDetail::create([
            'asset_transfer_id' => $latestTransfer->id,
            'asset_id' => $asset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($asset->fresh()->checkValidRecipient());

        $this->assertTrue($asset->fresh()->syncRecipientFromLatestTransferDetail());

        $asset = $asset->fresh();

        $this->assertSame($latestRecipient->id, $asset->recipient_id);
        $this->assertSame($latestEntity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
        $this->assertTrue($asset->checkValidRecipient());
    }

    public function test_sync_recipient_marks_asset_available_when_latest_recipient_is_general_affair(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang']);
        $fromUser = User::create(['name' => 'Pengirim']);
        $generalAffair = User::create(['name' => 'GA']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');

        $asset = Asset::create([
            'name' => 'Printer',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $fromUser->id,
            'recipient_business_entity_id' => $entity->id,
        ]);

        $transfer = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'BAST-GA',
            'from_user_id' => $fromUser->id,
            'to_user_id' => $generalAffair->id,
            'transfer_date' => now(),
        ]);

        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $this->assertTrue($asset->fresh()->syncRecipientFromLatestTransferDetail());

        $asset = $asset->fresh();

        $this->assertSame($generalAffair->id, $asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertSame(1, $asset->getRawOriginal('is_available'));
        $this->assertTrue($asset->checkValidRecipient());
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('condition_status')->default(AssetCondition::Available->value);
            $table->string('nbh_status')->default(NbhStatus::None->value);
            $table->date('nbh_reported_at')->nullable();
            $table->string('audit_document_path')->nullable();
            $table->string('nbh_document_path')->nullable();
            $table->text('nbh_notes')->nullable();
            $table->unsignedBigInteger('nbh_responsible_user_id')->nullable();
            $table->date('sold_at')->nullable();
            $table->string('sold_to')->nullable();
            $table->integer('sold_price')->nullable();
            $table->string('sale_document_path')->nullable();
            $table->text('sale_notes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('recipient_business_entity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id');
            $table->string('letter_number')->unique();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->timestamp('transfer_date')->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_transfer_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('equipment')->nullable();
            $table->timestamps();
        });
    }
}
