<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\NbhStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Mail\AssetNotificationMail;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetRequest;
use App\Models\AssetRequestApproval;
use App\Models\CustomAssetAttribute;
use App\Models\Division;
use App\Models\DivisionApprover;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssetRequestTest extends TestCase
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

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('division_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('division_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('level');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('imei1')->nullable();
            $table->string('imei2')->nullable();
            $table->string('type')->nullable();
            $table->integer('item_price')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('asset_location_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('condition_status')->default('available');
            $table->string('nbh_status')->default('none');
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
            $table->unsignedBigInteger('asset_request_id')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique()->nullable();
            $table->string('public_token')->unique()->nullable();
            $table->string('type')->default('pengadaan');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('asset_location_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('qty')->nullable();
            $table->integer('current_level')->default(1);
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->unsignedBigInteger('fulfilled_by_user_id')->nullable();
            $table->unsignedBigInteger('asset_transfer_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_request_id');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('qty')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('fulfilled_asset_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('public_token')->unique()->nullable();
            $table->integer('level');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_asset_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->boolean('required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('category_id')->nullable();
            $table->boolean('is_notifiable')->default(false);
            $table->string('notification_type')->nullable();
            $table->integer('notification_offset')->nullable();
            $table->date('fixed_notification_date')->nullable();
            $table->json('notification_channels')->nullable();
            $table->json('notification_recipient_user_ids')->nullable();
            $table->json('notification_recipient_emails')->nullable();
            $table->json('notification_recipient_whatsapp_numbers')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('custom_attribute_id')->nullable();
            $table->text('attribute_value')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_creates_asset_request_and_generates_reference_number_sequentially(): void
    {
        $division = Division::create(['name' => 'IT']);
        $user1 = User::create(['name' => 'John Doe']);
        $user2 = User::create(['name' => 'Jane Smith']);

        $request1 = AssetRequest::create([
            'user_id' => $user1->id,
            'division_id' => $division->id,
            'item_name' => 'MacBook Pro',
            'qty' => 1,
        ]);

        $year = date('Y');
        $this->assertEquals("REQ-{$year}-001", $request1->reference_number);
        // Should be auto-approved since no approvers are configured for IT division
        $this->assertEquals(RequestStatus::Approved, $request1->status);

        $request2 = AssetRequest::create([
            'user_id' => $user2->id,
            'division_id' => $division->id,
            'item_name' => 'Ergonomic Chair',
            'qty' => 2,
        ]);

        $this->assertEquals("REQ-{$year}-002", $request2->reference_number);
    }

    public function test_asset_request_generates_public_token_and_progress_url(): void
    {
        $division = Division::create(['name' => 'Public Tracking']);
        $user = User::create(['name' => 'Requester', 'email' => 'requester@example.com']);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'item_name' => 'Laptop Operasional',
            'qty' => 1,
        ])->fresh();

        $this->assertNotEmpty($request->public_token);
        $this->assertSame(
            route('public.asset-requests.show', $request->public_token),
            $request->publicProgressUrl()
        );
    }

    public function test_resend_current_pending_approver_notification_targets_active_level_with_public_url(): void
    {
        Http::fake();
        Mail::fake();

        $division = Division::create(['name' => 'Approval Reminder']);
        $requester = User::create(['name' => 'Requester', 'email' => 'requester@example.com']);
        $manager = User::create(['name' => 'Manager', 'email' => 'manager@example.com']);
        $director = User::create(['name' => 'Director', 'email' => 'director@example.com']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $manager->id, 'level' => 1]);
        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $director->id, 'level' => 2]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Server Rack',
            'qty' => 1,
        ])->fresh();

        Mail::fake();

        $result = $request->sendCurrentApprovalReminder();
        $approvalUrl = $request->currentPendingApproval()->publicApprovalUrl();

        $this->assertSame($manager->id, $result['recipient']->id);
        $this->assertStringContainsString($approvalUrl, $result['message']['whatsapp']);
        $this->assertStringContainsString('Pengajuan Aset: Pengingat', $result['message']['whatsapp']);
        $this->assertStringContainsString('Nama Aset: Server Rack', $result['message']['whatsapp']);
        $this->assertStringContainsString('Jumlah: 1', $result['message']['whatsapp']);
        $this->assertStringContainsString('Setujui atau tolak:', $result['message']['whatsapp']);
        $this->assertStringContainsString('IT Support', $result['message']['whatsapp']);
        $this->assertSame('Setujui atau Tolak', $result['message']['email']['cta_label']);
        $this->assertSame($approvalUrl, $result['message']['email']['cta_url']);
        $this->assertSame('approve', $result['message']['email']['cta_variant']);
        $this->assertStringNotContainsString('Level', $result['message']['whatsapp']);
        $this->assertStringNotContainsString('ke-1', $result['message']['whatsapp']);
        $this->assertStringNotContainsString('Halo ', $result['message']['whatsapp']);
        Mail::assertSent(AssetNotificationMail::class, function (AssetNotificationMail $mail) use ($manager, $approvalUrl): bool {
            return $mail->hasTo($manager->email)
                && str_contains($mail->body, $approvalUrl)
                && str_contains($mail->body, 'Setujui atau tolak:')
                && str_contains($mail->body, 'IT Support')
                && ($mail->email['cta_label'] ?? null) === 'Setujui atau Tolak'
                && ($mail->email['cta_url'] ?? null) === $approvalUrl
                && ! str_contains($mail->body, 'Level')
                && ! str_contains($mail->body, 'ke-1');
        });

        $request->approveCurrentLevel('ok', $manager);
        $request->refresh();
        Mail::fake();

        $nextResult = $request->sendCurrentApprovalReminder();
        $nextApprovalUrl = $request->currentPendingApproval()->publicApprovalUrl();

        $this->assertSame($director->id, $nextResult['recipient']->id);
        $this->assertStringContainsString($nextApprovalUrl, $nextResult['message']['whatsapp']);
        $this->assertStringContainsString('Pengajuan Aset: Pengingat', $nextResult['message']['whatsapp']);
        $this->assertStringNotContainsString('Level', $nextResult['message']['whatsapp']);
        $this->assertStringNotContainsString('ke-2', $nextResult['message']['whatsapp']);
        Mail::assertSent(AssetNotificationMail::class, function (AssetNotificationMail $mail) use ($director, $nextApprovalUrl): bool {
            return $mail->hasTo($director->email)
                && str_contains($mail->body, $nextApprovalUrl)
                && str_contains($mail->body, 'Setujui atau tolak:')
                && str_contains($mail->body, 'IT Support')
                && ($mail->email['cta_url'] ?? null) === $nextApprovalUrl
                && ! str_contains($mail->body, 'Level')
                && ! str_contains($mail->body, 'ke-2');
        });
    }

    public function test_resend_requester_progress_notification_uses_public_url(): void
    {
        Http::fake();
        Mail::fake();

        $division = Division::create(['name' => 'Requester Reminder']);
        $requester = User::create(['name' => 'Requester', 'email' => 'requester@example.com']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Laptop Operasional',
            'qty' => 1,
        ])->fresh();

        Mail::fake();

        $result = $request->sendRequesterProgressReminder();

        $this->assertSame($requester->id, $result['recipient']->id);
        $this->assertStringContainsString($request->publicProgressUrl(), $result['message']['whatsapp']);
        $this->assertStringContainsString($request->lifecycleStageLabel(), $result['message']['whatsapp']);
        $this->assertStringContainsString('Pengajuan Aset: Status', $result['message']['whatsapp']);
        $this->assertStringContainsString('Nama Aset: Laptop Operasional', $result['message']['whatsapp']);
        $this->assertStringContainsString('Jumlah: 1', $result['message']['whatsapp']);
        $this->assertStringContainsString('Lihat progress:', $result['message']['whatsapp']);
        $this->assertStringContainsString('IT Support', $result['message']['whatsapp']);
        $this->assertSame('Lihat Progress', $result['message']['email']['cta_label']);
        $this->assertSame('progress', $result['message']['email']['cta_variant']);
        $this->assertSame('FORM PENGAJUAN ASET', $result['message']['email']['form_title']);
        Mail::assertSent(AssetNotificationMail::class, function (AssetNotificationMail $mail) use ($requester, $request): bool {
            return $mail->hasTo($requester->email)
                && str_contains($mail->body, $request->publicProgressUrl())
                && str_contains($mail->body, $request->reference_number)
                && str_contains($mail->body, 'Lihat progress:')
                && str_contains($mail->body, 'IT Support')
                && ($mail->email['cta_label'] ?? null) === 'Lihat Progress'
                && ($mail->email['form_title'] ?? null) === 'FORM PENGAJUAN ASET';
        });
    }

    public function test_it_handles_multi_level_sequential_approval_flow(): void
    {
        $division = Division::create(['name' => 'Finance']);
        $requester = User::create(['name' => 'Alice']);
        $user1 = User::create(['name' => 'Manager Finance', 'whatsapp_number' => '08123456789']);
        $user2 = User::create(['name' => 'Director Finance', 'whatsapp_number' => '08987654321']);

        DivisionApprover::create([
            'division_id' => $division->id,
            'user_id' => $user1->id,
            'level' => 1,
        ]);

        DivisionApprover::create([
            'division_id' => $division->id,
            'user_id' => $user2->id,
            'level' => 2,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Monitor 24 inch',
            'qty' => 1,
        ]);

        $this->assertEquals(RequestStatus::Pending, $request->status);
        $this->assertEquals(1, $request->current_level);

        $this->assertDatabaseHas('asset_request_approvals', [
            'asset_request_id' => $request->id,
            'user_id' => $user1->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('asset_request_approvals', [
            'asset_request_id' => $request->id,
            'user_id' => $user2->id,
            'level' => 2,
            'status' => 'pending',
        ]);

        // Simulating Level 1 Approval
        $approval1 = AssetRequestApproval::where('asset_request_id', $request->id)
            ->where('level', 1)
            ->first();

        $approval1->update(['status' => 'approved']);
        $request->update(['current_level' => 2]);

        $this->assertEquals(RequestStatus::Pending, $request->status);
        $this->assertEquals(2, $request->current_level);

        // Simulating Level 2 Approval
        $approval2 = AssetRequestApproval::where('asset_request_id', $request->id)
            ->where('level', 2)
            ->first();

        $approval2->update(['status' => 'approved']);
        $request->update(['status' => RequestStatus::Approved]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);
    }

    public function test_it_handles_rejection_in_approval_flow(): void
    {
        $division = Division::create(['name' => 'Operations']);
        $requester = User::create(['name' => 'Bob']);
        $user = User::create(['name' => 'Ops Manager', 'whatsapp_number' => '08123456789']);

        DivisionApprover::create([
            'division_id' => $division->id,
            'user_id' => $user->id,
            'level' => 1,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Forklift',
            'qty' => 1,
        ]);

        $this->assertEquals(RequestStatus::Pending, $request->status);

        // Simulating Level 1 Rejection
        $approval = AssetRequestApproval::where('asset_request_id', $request->id)
            ->where('level', 1)
            ->first();

        $approval->update([
            'status' => 'rejected',
            'notes' => 'Budget constraints',
        ]);

        $request->update([
            'status' => RequestStatus::Rejected,
            'notes' => 'Ditolak oleh Ops Manager. Alasan: Budget constraints',
        ]);

        $this->assertEquals(RequestStatus::Rejected, $request->fresh()->status);
        $this->assertStringContainsString('Budget constraints', $request->fresh()->notes);
    }

    public function test_it_creates_penarikan_request_and_resolves_asset_details(): void
    {
        $division = Division::create(['name' => 'IT']);
        $user = User::create(['name' => 'John Doe']);

        $asset = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'LNV12345',
        ]);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => 'penarikan',
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals('Lenovo ThinkPad', $request->item_name);
        $this->assertEquals(1, $request->qty);
        $this->assertEquals(AssetRequestType::Penarikan, $request->type);
    }

    public function test_asset_request_can_hold_multiple_requested_asset_items_under_one_reference(): void
    {
        $division = Division::create(['name' => 'IT']);
        $user = User::create(['name' => 'John Doe']);

        $laptop = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'LNV12345',
            'recipient_id' => $user->id,
        ]);
        $phone = Asset::create([
            'name' => 'iPhone 15',
            'serial_number' => 'IPH12345',
            'recipient_id' => $user->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $laptop->id,
        ]);

        $request->items()->create([
            'asset_id' => $phone->id,
            'item_name' => $phone->name,
            'qty' => 1,
        ]);

        $request->refresh();

        $this->assertCount(2, $request->items);
        $this->assertSame([$laptop->id, $phone->id], $request->requestedAssetIds());
        $this->assertSame('2 aset: Lenovo ThinkPad, iPhone 15', $request->itemSummaryLabel());
    }

    public function test_it_creates_perbaikan_request_and_resolves_asset_details(): void
    {
        $division = Division::create(['name' => 'IT']);
        $user = User::create(['name' => 'John Doe']);

        $asset = Asset::create([
            'name' => 'Toyota Avanza',
            'serial_number' => 'B 1234 AB',
        ]);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => 'perbaikan',
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals('Toyota Avanza', $request->item_name);
        $this->assertEquals(1, $request->qty);
        $this->assertEquals(AssetRequestType::Perbaikan, $request->type);
    }

    public function test_it_creates_pengadaan_request_with_attachment(): void
    {
        $division = Division::create(['name' => 'IT']);
        $user = User::create(['name' => 'John Doe']);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Projector Epson',
            'qty' => 3,
            'attachment' => ['asset-requests/dummy-proposal.pdf'],
        ]);

        $this->assertEquals('Projector Epson', $request->item_name);
        $this->assertEquals(3, $request->qty);
        $this->assertEquals(AssetRequestType::Pengadaan, $request->type);
        $this->assertEquals(['asset-requests/dummy-proposal.pdf'], $request->attachment);
    }

    public function test_approve_current_level_records_decision_and_advances(): void
    {
        $division = Division::create(['name' => 'Procurement']);
        $requester = User::create(['name' => 'Requester']);
        $manager = User::create(['name' => 'Manager', 'whatsapp_number' => '08111111111']);
        $director = User::create(['name' => 'Director', 'whatsapp_number' => '08222222222']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $manager->id, 'level' => 1]);
        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $director->id, 'level' => 2]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Server Rack',
            'qty' => 1,
        ]);

        // Level 1 approval oleh approver yang benar.
        $request->approveCurrentLevel('ok', $manager);

        $request->refresh();
        $this->assertEquals(2, $request->current_level);
        $this->assertEquals(RequestStatus::Pending, $request->status);

        $approval1 = AssetRequestApproval::where('asset_request_id', $request->id)->where('level', 1)->first();
        $this->assertEquals(RequestStatus::Approved, $approval1->status);
        $this->assertEquals($manager->id, $approval1->decided_by_user_id);
        $this->assertNotNull($approval1->decided_at);

        // Level 2 (final) approval -> status Approved.
        $request->approveCurrentLevel(null, $director);
        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);
    }

    public function test_approve_current_level_rejects_non_approver(): void
    {
        $division = Division::create(['name' => 'Legal']);
        $requester = User::create(['name' => 'Requester']);
        $approver = User::create(['name' => 'Approver', 'whatsapp_number' => '08111111111']);
        $intruder = User::create(['name' => 'Intruder']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $approver->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Item',
            'qty' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $request->approveCurrentLevel(null, $intruder);
    }

    public function test_approve_current_level_rejects_self_approval(): void
    {
        $division = Division::create(['name' => 'Self']);
        $requester = User::create(['name' => 'Requester']);

        // Pemohon juga terdaftar sebagai approver level 1 — tidak boleh menyetujui sendiri.
        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $requester->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Item',
            'qty' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $request->approveCurrentLevel(null, $requester);
    }

    public function test_approve_current_level_rejects_when_not_pending(): void
    {
        $division = Division::create(['name' => 'Done']);
        $requester = User::create(['name' => 'Requester']);
        $approver = User::create(['name' => 'Approver', 'whatsapp_number' => '08111111111']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $approver->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Item',
            'qty' => 1,
        ]);

        // Final approval -> Approved.
        $request->approveCurrentLevel(null, $approver);
        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);

        // Approve lagi pada request yang sudah Approved harus ditolak.
        $this->expectException(AuthorizationException::class);
        $request->approveCurrentLevel(null, $approver);
    }

    public function test_reject_current_level_records_decided_by(): void
    {
        $division = Division::create(['name' => 'Reject']);
        $requester = User::create(['name' => 'Requester']);
        $approver = User::create(['name' => 'Approver', 'whatsapp_number' => '08111111111']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $approver->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Item',
            'qty' => 1,
        ]);

        $request->rejectCurrentLevel('Tidak sesuai kebutuhan', $approver);

        $this->assertEquals(RequestStatus::Rejected, $request->fresh()->status);
        $approval = AssetRequestApproval::where('asset_request_id', $request->id)->where('level', 1)->first();
        $this->assertEquals(RequestStatus::Rejected, $approval->status);
        $this->assertEquals($approver->id, $approval->decided_by_user_id);
        $this->assertNotNull($approval->decided_at);
        $this->assertStringContainsString('Tidak sesuai kebutuhan', $request->fresh()->notes);
    }

    public function test_reject_current_level_rejects_non_approver(): void
    {
        $division = Division::create(['name' => 'Reject2']);
        $requester = User::create(['name' => 'Requester']);
        $approver = User::create(['name' => 'Approver', 'whatsapp_number' => '08111111111']);
        $intruder = User::create(['name' => 'Intruder']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $approver->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Item',
            'qty' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $request->rejectCurrentLevel('alasan', $intruder);
    }

    public function test_approved_pengadaan_does_not_auto_create_asset(): void
    {
        // Approval hanya persetujuan; tindak lanjut (pembuatan aset) tetap operator.
        $division = Division::create(['name' => 'NoApprover']);
        $requester = User::create(['name' => 'Requester']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 2,
        ]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);
        $this->assertCount(0, $request->createdAssets);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_created_assets_relation_links_asset_to_request(): void
    {
        $division = Division::create(['name' => 'Link']);
        $requester = User::create(['name' => 'Requester']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $asset = Asset::create([
            'name' => 'Laptop',
            'asset_request_id' => $request->id,
        ]);

        $this->assertTrue($request->createdAssets->contains($asset->id));
        $this->assertEquals($request->id, $asset->assetRequest->id);
    }

    public function test_fulfill_pengadaan_creates_asset_and_marks_fulfilled(): void
    {
        $division = Division::create(['name' => 'NoApproverPengadaan']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop Dell',
            'qty' => 2,
        ]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);

        $asset = $request->fulfillPengadaan([
            'category_id' => 1,
            'brand_id' => 1,
            'business_entity_id' => 1,
        ], $operator);

        $this->assertEquals('Laptop Dell', $asset->name);
        $this->assertEquals(2, $asset->qty);
        $this->assertEquals(AssetCondition::Available, $asset->condition_status);
        $this->assertEquals($request->id, $asset->asset_request_id);

        $request->refresh();
        $this->assertNotNull($request->fulfilled_at);
        $this->assertEquals($operator->id, $request->fulfilled_by_user_id);
        $this->assertTrue($request->isFulfilled());
    }

    public function test_multi_item_pengadaan_is_fulfilled_only_after_all_items_have_assets(): void
    {
        $division = Division::create(['name' => 'NoApproverMultiPengadaan']);
        $requester = User::create(['name' => 'Requester', 'business_entity_id' => 7]);
        $operator = User::create(['name' => 'Operator']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Macbook Air M2',
            'qty' => 2,
        ]);
        $request->items()->create([
            'item_name' => 'Monitor 27 inch',
            'qty' => 3,
        ]);

        $firstAsset = $request->fulfillPengadaan([
            'category_id' => 1,
            'brand_id' => 1,
            'business_entity_id' => 7,
        ], $operator);

        $request->refresh();
        $request->load('items');

        $this->assertSame('Macbook Air M2', $firstAsset->name);
        $this->assertSame(2, $firstAsset->qty);
        $this->assertFalse($request->isFulfilled());
        $this->assertNull($request->fulfilled_at);
        $this->assertSame($firstAsset->id, $request->items[0]->fulfilled_asset_id);
        $this->assertNotNull($request->items[0]->fulfilled_at);
        $this->assertNull($request->items[1]->fulfilled_asset_id);

        $prefill = CreateAsset::prefillDataFromAssetRequest($request);
        $this->assertSame('Monitor 27 inch', $prefill['name']);
        $this->assertSame(3, $prefill['qty']);

        $secondAsset = $request->fulfillPengadaan([
            'category_id' => 1,
            'brand_id' => 1,
            'business_entity_id' => 7,
        ], $operator);

        $request->refresh();

        $this->assertSame('Monitor 27 inch', $secondAsset->name);
        $this->assertSame(3, $secondAsset->qty);
        $this->assertTrue($request->isFulfilled());
        $this->assertSame($operator->id, $request->fulfilled_by_user_id);
    }

    public function test_asset_create_page_can_prefill_from_approved_pengadaan_request(): void
    {
        $division = Division::create(['name' => 'NoApproverAssetCreatePrefill']);
        $requester = User::create([
            'name' => 'Requester',
            'business_entity_id' => 7,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop Dell',
            'qty' => 2,
            'asset_location_id' => 42,
        ]);

        $prefill = CreateAsset::prefillDataFromAssetRequest($request);

        $this->assertSame('Laptop Dell', $prefill['name']);
        $this->assertSame(2, $prefill['qty']);
        $this->assertSame(7, $prefill['business_entity_id']);
        $this->assertSame($request->id, $prefill['asset_request_id']);
        $this->assertSame(42, $prefill['asset_location_id']);
        $this->assertSame(AssetCondition::Available->value, $prefill['condition_status']);
        $this->assertSame($request->nextUnfulfilledPengadaanItem()?->id, $prefill['asset_request_item_id']);
    }

    public function test_mark_fulfilled_by_asset_can_target_explicit_item_id(): void
    {
        $division = Division::create(['name' => 'ExplicitItemFulfill']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Item Pertama',
            'qty' => 1,
        ]);
        $secondItem = $request->items()->create([
            'item_name' => 'Item Kedua',
            'qty' => 1,
        ]);
        $request->load('items');
        $firstItem = $request->items->first();

        $asset = Asset::create([
            'name' => 'Item Kedua',
            'condition_status' => AssetCondition::Available,
            'asset_request_id' => $request->id,
        ]);

        $request->markFulfilledByAsset($asset, $operator, $secondItem->id);

        $request->refresh()->load('items');
        $this->assertNull($firstItem->fresh()->fulfilled_asset_id);
        $this->assertSame($asset->id, $secondItem->fresh()->fulfilled_asset_id);
        $this->assertFalse($request->isFulfilled());
    }

    public function test_asset_request_can_be_marked_fulfilled_by_created_asset(): void
    {
        $division = Division::create(['name' => 'NoApproverAssetCreateFulfill']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop Dell',
            'qty' => 1,
        ]);

        $asset = Asset::create([
            'name' => 'Laptop Dell',
            'condition_status' => AssetCondition::Available,
            'asset_request_id' => $request->id,
        ]);

        $request->markFulfilledByAsset($asset, $operator);

        $request->refresh();

        $this->assertNotNull($request->fulfilled_at);
        $this->assertSame($operator->id, $request->fulfilled_by_user_id);
    }

    public function test_fulfill_pengadaan_saves_photo_and_custom_attributes(): void
    {
        $division = Division::create(['name' => 'NoApproverPengadaanWithAttributes']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $textAttribute = CustomAssetAttribute::create([
            'name' => 'Nomor Polisi',
            'type' => CustomAssetAttribute::TYPE_TEXT,
            'category_id' => [10],
            'is_active' => true,
        ]);

        $documentAttribute = CustomAssetAttribute::create([
            'name' => 'STNK',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'category_id' => [10],
            'is_active' => true,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Motor Operasional',
            'qty' => 1,
        ]);

        $asset = $request->fulfillPengadaan([
            'category_id' => 10,
            'image' => 'assets/motor.jpg',
            'type' => 'Honda Vario 160',
            'attributes' => [
                [
                    'custom_attribute_id' => $textAttribute->id,
                    'attribute_value' => 'B 1234 XYZ',
                ],
                [
                    'custom_attribute_id' => $documentAttribute->id,
                    'document_number' => 'STNK-001',
                    'document_expires_at' => '2027-06-26',
                    'document_file_path' => ['asset-documents/stnk.pdf'],
                    'document_notes' => 'Dokumen awal dari pengadaan.',
                ],
            ],
        ], $operator);

        $this->assertSame('assets/motor.jpg', $asset->image);
        $this->assertSame('Honda Vario 160', $asset->type);

        $this->assertDatabaseHas('asset_attributes', [
            'asset_id' => $asset->id,
            'custom_attribute_id' => $textAttribute->id,
            'attribute_value' => 'B 1234 XYZ',
        ]);

        $documentRow = AssetAttribute::query()
            ->where('asset_id', $asset->id)
            ->where('custom_attribute_id', $documentAttribute->id)
            ->firstOrFail();

        $payload = json_decode($documentRow->attribute_value, true);

        $this->assertSame('STNK-001', $payload['document_number']);
        $this->assertSame('2027-06-26', $payload['expires_at']);
        $this->assertSame('asset-documents/stnk.pdf', $payload['document_path']);
        $this->assertSame('Dokumen awal dari pengadaan.', $payload['notes']);
    }

    public function test_fulfill_pengadaan_rejects_when_not_approved(): void
    {
        $division = Division::create(['name' => 'HasApprover']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);
        $approver = User::create(['name' => 'Approver', 'whatsapp_number' => '08123456789']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $approver->id, 'level' => 1]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $this->assertEquals(RequestStatus::Pending, $request->fresh()->status);

        $this->expectException(AuthorizationException::class);
        $request->fulfillPengadaan([], $operator);
    }

    public function test_fulfill_pengadaan_rejects_double_fulfillment(): void
    {
        $division = Division::create(['name' => 'NoApproverDouble']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $request->fulfillPengadaan([], $operator);

        $this->expectException(\RuntimeException::class);
        $request->fulfillPengadaan([], $operator);
    }

    public function test_fulfill_perbaikan_marks_asset_damaged_and_pending_nbh(): void
    {
        $division = Division::create(['name' => 'NoApproverPerbaikan']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $asset = Asset::create([
            'name' => 'Forklift',
            'condition_status' => 'transferred',
            'recipient_id' => $requester->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'perbaikan',
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);

        $updatedAsset = $request->fulfillPerbaikan($operator);

        $this->assertEquals(AssetCondition::Damaged, $updatedAsset->condition_status);
        $this->assertEquals(NbhStatus::Pending, $updatedAsset->nbh_status);

        $request->refresh();
        $this->assertNotNull($request->fulfilled_at);
        $this->assertEquals($operator->id, $request->fulfilled_by_user_id);
    }

    public function test_complete_repair_processing_marks_asset_operational_and_nbh_resolved(): void
    {
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $asset = Asset::create([
            'name' => 'Laptop Repair Done',
            'condition_status' => AssetCondition::Damaged,
            'nbh_status' => NbhStatus::Pending,
            'recipient_id' => $requester->id,
            'audit_document_path' => 'asset-audit/old.pdf',
        ]);

        $asset->completeRepairProcessing($operator, [
            'audit_document_path' => 'asset-audit/service-report.pdf',
            'nbh_document_path' => 'asset-nbh/repair-close.pdf',
            'nbh_notes' => 'Selesai servis dan sudah bisa digunakan lagi.',
        ]);

        $asset->refresh();

        $this->assertEquals(AssetCondition::Transferred, $asset->condition_status);
        $this->assertEquals(NbhStatus::Resolved, $asset->nbh_status);
        $this->assertFalse($asset->is_available);
        $this->assertSame($operator->id, $asset->nbh_responsible_user_id);
        $this->assertSame('asset-audit/service-report.pdf', $asset->audit_document_path);
        $this->assertSame('asset-nbh/repair-close.pdf', $asset->nbh_document_path);
        $this->assertSame('Selesai servis dan sudah bisa digunakan lagi.', $asset->nbh_notes);
    }

    public function test_fulfill_rejects_wrong_type(): void
    {
        $division = Division::create(['name' => 'NoApproverWrong']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        // Request perbaikan (auto-approved) lalu mencoba fulfillPengadaan -> salah type.
        $asset = Asset::create(['name' => 'Forklift', 'condition_status' => 'transferred', 'recipient_id' => $requester->id]);
        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'perbaikan',
            'asset_id' => $asset->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $request->fulfillPengadaan([], $operator);
    }

    public function test_lock_state_excludes_assets_with_open_penarikan_or_perbaikan_request(): void
    {
        $division = Division::create(['name' => 'LockState']);
        $requester = User::create(['name' => 'Requester']);

        $lockedAsset = Asset::create(['name' => 'Locked', 'condition_status' => 'transferred', 'recipient_id' => $requester->id]);
        $freeAsset = Asset::create(['name' => 'Free', 'condition_status' => 'transferred', 'recipient_id' => $requester->id]);

        // Pengajuan penarikan tanpa approver -> auto Approved, belum fulfilled -> aset terkunci.
        AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'penarikan',
            'asset_id' => $lockedAsset->id,
        ]);

        $visible = Asset::notLockedForOpenRequest()->pluck('id')->all();

        $this->assertNotContains($lockedAsset->id, $visible);
        $this->assertContains($freeAsset->id, $visible);
    }

    public function test_lock_state_releases_asset_after_fulfillment(): void
    {
        $division = Division::create(['name' => 'LockStateRelease']);
        $requester = User::create(['name' => 'Requester']);
        $operator = User::create(['name' => 'Operator']);

        $asset = Asset::create(['name' => 'ToRepair', 'condition_status' => 'transferred', 'recipient_id' => $requester->id]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => 'perbaikan',
            'asset_id' => $asset->id,
        ]);

        // Sebelum fulfill: terkunci.
        $this->assertNotContains($asset->id, Asset::notLockedForOpenRequest()->pluck('id')->all());

        $request->fulfillPerbaikan($operator);

        // Setelah fulfill (fulfilled_at terisi): kunci lepas.
        $this->assertContains($asset->id, Asset::notLockedForOpenRequest()->pluck('id')->all());
    }

    public function test_item_age_handles_missing_purchase_date(): void
    {
        $asset = Asset::create(['name' => 'Aset Tanpa Tanggal Beli']);

        $this->assertSame('-', $asset->item_age);
    }

    public function test_reference_number_sequence_continues_after_999(): void
    {
        $division = Division::create(['name' => 'RefSeq']);
        $user = User::create(['name' => 'Requester']);
        $year = date('Y');

        // Seed 999 and 1000. String DESC would wrongly treat 999 as latest
        // (because "999" > "1000"), producing a duplicate 1000.
        DB::table('asset_requests')->insert([
            [
                'reference_number' => "REQ-{$year}-999",
                'public_token' => 'token-ref-999',
                'type' => 'pengadaan',
                'user_id' => $user->id,
                'division_id' => $division->id,
                'item_name' => 'Seed 999',
                'qty' => 1,
                'status' => 'approved',
                'current_level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'reference_number' => "REQ-{$year}-1000",
                'public_token' => 'token-ref-1000',
                'type' => 'pengadaan',
                'user_id' => $user->id,
                'division_id' => $division->id,
                'item_name' => 'Seed 1000',
                'qty' => 1,
                'status' => 'approved',
                'current_level' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $next = AssetRequest::generateReferenceNumber();

        $this->assertSame("REQ-{$year}-1001", $next);
    }

    public function test_sync_items_from_array_keeps_all_items_including_first(): void
    {
        $division = Division::create(['name' => 'SyncItems']);
        $user = User::create(['name' => 'Requester']);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop Lama',
            'qty' => 1,
        ]);
        $request->items()->create([
            'item_name' => 'Monitor Lama',
            'qty' => 1,
        ]);

        $request->syncItemsFromArray([
            ['item_name' => 'Laptop Baru', 'qty' => 2],
            ['item_name' => 'Monitor Baru', 'qty' => 3],
            ['item_name' => 'Keyboard Baru', 'qty' => 1],
        ]);

        $request->refresh()->load('items');

        $this->assertSame('Laptop Baru', $request->item_name);
        $this->assertSame(2, $request->qty);
        $this->assertCount(3, $request->items);
        $this->assertSame(['Laptop Baru', 'Monitor Baru', 'Keyboard Baru'], $request->items->pluck('item_name')->all());
        $this->assertSame([2, 3, 1], $request->items->pluck('qty')->all());
    }

    public function test_pending_material_scope_change_rebuilds_approval_chain(): void
    {
        $oldDivision = Division::create(['name' => 'Old Division']);
        $newDivision = Division::create(['name' => 'New Division']);
        $requester = User::create(['name' => 'Requester']);
        $oldApprover = User::create(['name' => 'Old Approver', 'email' => 'old@example.com']);
        $newApprover = User::create(['name' => 'New Approver', 'email' => 'new@example.com']);

        DivisionApprover::create([
            'division_id' => $oldDivision->id,
            'user_id' => $oldApprover->id,
            'level' => 1,
        ]);
        DivisionApprover::create([
            'division_id' => $newDivision->id,
            'user_id' => $newApprover->id,
            'level' => 1,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $oldDivision->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 1,
            'description' => 'Awal',
        ]);

        $this->assertEquals(RequestStatus::Pending, $request->status);
        $this->assertDatabaseHas('asset_request_approvals', [
            'asset_request_id' => $request->id,
            'user_id' => $oldApprover->id,
        ]);

        $request->update([
            'division_id' => $newDivision->id,
            'description' => 'Diubah',
        ]);
        $request->resetAndRebuildApprovalsForMaterialChange();

        $request->refresh();
        $this->assertEquals(RequestStatus::Pending, $request->status);
        $this->assertEquals(1, $request->current_level);
        $this->assertDatabaseMissing('asset_request_approvals', [
            'asset_request_id' => $request->id,
            'user_id' => $oldApprover->id,
        ]);
        $this->assertDatabaseHas('asset_request_approvals', [
            'asset_request_id' => $request->id,
            'user_id' => $newApprover->id,
            'level' => 1,
            'status' => 'pending',
        ]);
    }

    public function test_material_scope_is_not_editable_after_approved(): void
    {
        $division = Division::create(['name' => 'LockedScope']);
        $user = User::create(['name' => 'Requester']);

        $request = AssetRequest::create([
            'user_id' => $user->id,
            'division_id' => $division->id,
            'type' => 'pengadaan',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);
        $this->assertFalse($request->fresh()->isMaterialScopeEditable());
    }
}
