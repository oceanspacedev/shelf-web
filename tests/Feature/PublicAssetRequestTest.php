<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\AssetRequestApproval;
use App\Models\Division;
use App\Models\DivisionApprover;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAssetRequestTest extends TestCase
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
        Storage::fake('public');
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->timestamps();
        });

        Schema::create('business_entities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
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
            $table->string('serial_number')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
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
    }

    public function test_public_asset_request_page_renders(): void
    {
        $response = $this->get(route('public.asset-requests.index'));

        $response->assertStatus(200);
        $response->assertSee('Tidak ada / Lainnya');
        $response->assertSee('Badan Usaha');
        $response->assertSee('Posisi');
        $response->assertSee('No. WhatsApp');
        $response->assertSee('Nama Aset yang Diajukan');
        $response->assertSee('Tambah item pengadaan');
    }

    public function test_root_path_redirects_to_public_asset_request_form(): void
    {
        $this->get('/')->assertRedirect(route('public.asset-requests.index'));
    }

    public function test_public_asset_request_division_field_is_searchable(): void
    {
        $finance = Division::create(['name' => 'Finance Department']);
        Division::create(['name' => 'IT Department']);

        $response = $this->get(route('public.asset-requests.index'));

        $response->assertOk();
        $response->assertSee('id="division_id"', false);
        $response->assertSee('name="division_id"', false);
        $response->assertSee('id="division-trigger"', false);
        $response->assertSee('id="division-search-input"', false);
        $response->assertSee('placeholder="Cari divisi..."', false);
        $response->assertSee("filterDropdown('division', this.value)", false);
        $response->assertSee('onclick=\'selectDivision("'.$finance->id.'", "Finance Department")\'', false);
        $response->assertSee('Finance Department');
    }

    public function test_public_asset_request_page_exposes_existing_users_and_assets_to_guests(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'Public Applicant',
            'whatsapp_number' => '081234567890',
            'email' => 'public@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);

        Asset::create([
            'name' => 'Shared Laptop',
            'serial_number' => 'PUBLIC-SN-001',
            'recipient_id' => $user->id,
        ]);

        $response = $this->get(route('public.asset-requests.index'));

        $response->assertOk();
        $response->assertSee('Public Applicant');
        $response->assertSee('Shared Laptop');
        $response->assertSee('PUBLIC-SN-001');
    }

    public function test_authenticated_public_form_preselects_current_user_while_still_showing_public_applicants(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $otherUser = User::create([
            'name' => 'Jane Public',
            'whatsapp_number' => '089876543210',
            'email' => 'jane@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);

        Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'TP-001',
            'recipient_id' => $user->id,
        ]);
        Asset::create([
            'name' => 'Dell Latitude',
            'serial_number' => 'DL-002',
            'recipient_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->get(route('public.asset-requests.index'));

        $response->assertOk();
        $response->assertSee('John Doe');
        $response->assertSee('Jane Public');
        $response->assertSee('Lenovo ThinkPad');
        $response->assertSee('Dell Latitude');
        $response->assertSee('const defaultApplicantId = "'.$user->id.'";', false);
        $response->assertSee('selectDefaultApplicantIfAvailable();', false);
    }

    public function test_public_asset_request_validation_fails_for_invalid_data(): void
    {
        $response = $this->postJson(route('public.asset-requests.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'type',
            'applicant_name',
            'whatsapp_number',
            'email',
            'business_entity_id',
            'division_id',
        ]);
    }

    public function test_public_asset_request_creates_pengadaan_request(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);

        $attachment1 = UploadedFile::fake()->create('document.pdf', 500);
        $attachment2 = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 2,
            'description' => 'For new developers',
            'attachments' => [$attachment1, $attachment2],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pengajuan aset berhasil dibuat.',
        ]);

        $this->assertDatabaseHas('asset_requests', [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 2,
            'description' => 'For new developers',
            'status' => 'approved', // auto-approved because no approver is configured for IT
        ]);

        $request = AssetRequest::first();
        $response->assertJsonPath('progress_url', route('public.asset-requests.show', $request->public_token));
        $this->assertCount(2, $request->attachment);
        Storage::disk('public')->assertExists($request->attachment[0]);
        Storage::disk('public')->assertExists($request->attachment[1]);
    }

    public function test_public_status_page_shows_request_progress_by_unique_token(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $requester = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);

        $request = AssetRequest::create([
            'type' => 'pengadaan',
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 2,
            'description' => 'For new developers',
        ])->fresh(['user', 'division', 'items.asset', 'approvals.user']);

        $response = $this->get(route('public.asset-requests.show', $request->public_token));

        $response->assertOk();
        $response->assertSee($request->reference_number);
        $response->assertSee('Macbook Air M2');
        $response->assertSee($request->lifecycleStageLabel());
        $response->assertSee($request->nextStepLabel());

        $this->get(route('public.asset-requests.show', 'token-tidak-valid'))->assertNotFound();
    }

    public function test_public_approval_page_can_approve_current_pending_level_by_unique_token(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $requester = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $manager = User::create(['name' => 'Manager IT', 'email' => 'manager@example.com']);
        $director = User::create(['name' => 'Director IT', 'email' => 'director@example.com']);
        $division = Division::create(['name' => 'IT Department']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $manager->id, 'level' => 1]);
        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $director->id, 'level' => 2]);

        $request = AssetRequest::create([
            'type' => 'pengadaan',
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 1,
        ])->fresh();

        $approval = $request->currentPendingApproval();

        $this->get(route('public.asset-requests.approval', $approval->public_token))
            ->assertOk()
            ->assertSee($request->reference_number)
            ->assertSee('Manager IT')
            ->assertSee('Setujui')
            ->assertSee('Tolak');

        $response = $this->post(route('public.asset-requests.approval.approve', $approval->public_token), [
            'notes' => 'Sesuai kebutuhan operasional.',
        ]);

        $response->assertRedirect($request->publicProgressUrl());

        $this->assertDatabaseHas('asset_request_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
            'decided_by_user_id' => $manager->id,
            'notes' => 'Sesuai kebutuhan operasional.',
        ]);

        $request->refresh();
        $this->assertSame(2, $request->current_level);
        $this->assertSame('pending', $request->status->value);

        $nextApproval = AssetRequestApproval::where('asset_request_id', $request->id)
            ->where('level', 2)
            ->firstOrFail();

        $this->assertSame('pending', $nextApproval->status->value);
    }

    public function test_public_approval_page_uses_single_notes_field_for_both_decisions(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $requester = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $manager = User::create(['name' => 'Manager IT', 'email' => 'manager@example.com']);
        $division = Division::create(['name' => 'IT Department']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $manager->id, 'level' => 1]);

        $request = AssetRequest::create([
            'type' => 'pengadaan',
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 1,
        ])->fresh();

        $approval = $request->currentPendingApproval();

        $content = $this->get(route('public.asset-requests.approval', $approval->public_token))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, substr_count($content, 'name="notes"'));
        $this->assertStringContainsString('id="decision-form"', $content);
        $this->assertStringContainsString('id="decision-notes"', $content);
        $this->assertStringNotContainsString('id="approve-form"', $content);
        $this->assertStringNotContainsString('id="reject-form"', $content);
        $this->assertStringNotContainsString('id="approve-notes"', $content);
        $this->assertStringNotContainsString('id="reject-notes"', $content);
    }

    public function test_public_approval_page_can_reject_current_pending_level_by_unique_token(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $requester = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $manager = User::create(['name' => 'Manager IT', 'email' => 'manager@example.com']);
        $division = Division::create(['name' => 'IT Department']);

        DivisionApprover::create(['division_id' => $division->id, 'user_id' => $manager->id, 'level' => 1]);

        $request = AssetRequest::create([
            'type' => 'pengadaan',
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 1,
        ])->fresh();

        $approval = $request->currentPendingApproval();

        $response = $this->post(route('public.asset-requests.approval.reject', $approval->public_token), [
            'notes' => 'Budget belum tersedia.',
        ]);

        $response->assertRedirect($request->publicProgressUrl());

        $this->assertDatabaseHas('asset_request_approvals', [
            'id' => $approval->id,
            'status' => 'rejected',
            'decided_by_user_id' => $manager->id,
            'notes' => 'Budget belum tersedia.',
        ]);

        $request->refresh();
        $this->assertSame('rejected', $request->status->value);
        $this->assertStringContainsString('Budget belum tersedia.', $request->notes);
    }

    public function test_public_asset_request_creates_single_pengadaan_request_with_multiple_items(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'items' => [
                ['item_name' => 'Macbook Air M2', 'qty' => 2],
                ['item_name' => 'Monitor 27 inch', 'qty' => 3],
            ],
            'description' => 'New team equipment',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pengajuan aset berhasil dibuat dengan 2 item.',
            'created_count' => 1,
            'item_count' => 2,
        ]);

        $this->assertDatabaseCount('asset_requests', 1);
        $request = AssetRequest::firstOrFail();

        $this->assertDatabaseHas('asset_request_items', [
            'asset_request_id' => $request->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('asset_request_items', [
            'asset_request_id' => $request->id,
            'item_name' => 'Monitor 27 inch',
            'qty' => 3,
        ]);
    }

    public function test_existing_applicant_with_complete_contact_can_submit_without_contact_fields(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'item_name' => 'Macbook Air M2',
            'qty' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('lifecycle_stage', 'Disetujui - Perlu Buat Aset');
        $response->assertJsonPath('next_step', 'Operator membuat data aset dari pengajuan ini');

        $this->assertDatabaseHas('asset_requests', [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'item_name' => 'Macbook Air M2',
        ]);
        $this->assertSame('081234567890', $user->fresh()->whatsapp_number);
        $this->assertSame('john@example.com', $user->fresh()->email);
    }

    public function test_existing_applicant_must_complete_missing_contact_only(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);

        $missingWhatsapp = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'item_name' => 'Keyboard',
            'qty' => 1,
        ]);

        $missingWhatsapp->assertStatus(422);
        $missingWhatsapp->assertJsonValidationErrors(['whatsapp_number']);
        $missingWhatsapp->assertJsonMissingValidationErrors(['email']);

        $completed = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'item_name' => 'Keyboard',
            'qty' => 1,
        ]);

        $completed->assertStatus(200);
        $completed->assertJson(['success' => true]);
        $this->assertSame('081234567890', $user->fresh()->whatsapp_number);
        $this->assertSame('john@example.com', $user->fresh()->email);
    }

    public function test_public_asset_request_creates_penarikan_request_and_resolves_asset_details(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);
        $asset = Asset::create([
            'name' => 'Asus ROG',
            'serial_number' => 'ROG123',
            'recipient_id' => $user->id,
        ]);

        $attachment = UploadedFile::fake()->image('receipt.png');

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
            'description' => 'Unused asset',
            'attachments' => [$attachment],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('asset_requests', [
            'type' => 'penarikan',
            'asset_id' => $asset->id,
            'item_name' => 'Asus ROG',
            'qty' => 1,
            'user_id' => $user->id,
            'division_id' => $division->id,
        ]);
    }

    public function test_public_asset_request_creates_single_penarikan_request_with_multiple_asset_items(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);
        $laptop = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'TP-001',
            'recipient_id' => $user->id,
        ]);
        $phone = Asset::create([
            'name' => 'iPhone 15',
            'serial_number' => 'IP-002',
            'recipient_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_ids' => [$laptop->id, $phone->id],
            'description' => 'User resign, tarik semua aset',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pengajuan aset berhasil dibuat dengan 2 item.',
            'created_count' => 1,
            'item_count' => 2,
        ]);
        $response->assertJsonMissingPath('reference_numbers');

        $this->assertDatabaseHas('asset_requests', [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'division_id' => $division->id,
        ]);

        $this->assertDatabaseCount('asset_requests', 1);
        $request = AssetRequest::firstOrFail();

        $this->assertDatabaseHas('asset_request_items', [
            'asset_request_id' => $request->id,
            'asset_id' => $laptop->id,
            'item_name' => 'Lenovo ThinkPad',
            'qty' => 1,
        ]);
        $this->assertDatabaseHas('asset_request_items', [
            'asset_request_id' => $request->id,
            'asset_id' => $phone->id,
            'item_name' => 'iPhone 15',
            'qty' => 1,
        ]);
    }

    public function test_guest_can_submit_existing_applicant_asset_request_through_public_flow(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $division = Division::create(['name' => 'IT Department']);
        $asset = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'TP-001',
            'recipient_id' => $user->id,
        ]);

        $response = $this->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
            'description' => 'Tarik aset lewat form public',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Pengajuan aset berhasil dibuat.',
        ]);

        $request = AssetRequest::firstOrFail();
        $response->assertJsonPath('progress_url', route('public.asset-requests.show', $request->public_token));

        $this->assertDatabaseHas('asset_requests', [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
            'item_name' => 'Lenovo ThinkPad',
        ]);
    }

    public function test_public_asset_request_rejects_bulk_asset_selection_with_unowned_asset(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $otherUser = User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $division = Division::create(['name' => 'IT Department']);
        $ownedAsset = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'recipient_id' => $user->id,
        ]);
        $unownedAsset = Asset::create([
            'name' => 'Asus ROG',
            'recipient_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_ids' => [$ownedAsset->id, $unownedAsset->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['asset_ids']);
        $this->assertDatabaseCount('asset_requests', 0);
    }

    public function test_rejected_public_existing_user_request_does_not_mutate_user_profile_or_contact(): void
    {
        $originalBusinessEntity = $this->createBusinessEntity();
        $newBusinessEntity = (object) [
            'id' => DB::table('business_entities')->insertGetId([
                'name' => 'PT Malicious Update',
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ];
        $newJobTitleId = DB::table('job_titles')->insertGetId([
            'title' => 'Injected Position',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'John Doe',
            'business_entity_id' => $originalBusinessEntity->id,
        ]);
        $otherUser = User::create(['name' => 'Jane Doe']);
        $division = Division::create(['name' => 'IT Department']);
        $asset = Asset::create([
            'name' => 'Asus ROG',
            'recipient_id' => $otherUser->id,
        ]);

        $response = $this->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'whatsapp_number' => '081999999999',
            'email' => 'changed@example.com',
            'job_title_id' => $newJobTitleId,
            'business_entity_id' => $newBusinessEntity->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
        ]);

        $response->assertStatus(422);

        $user->refresh();
        $this->assertSame($originalBusinessEntity->id, $user->business_entity_id);
        $this->assertNull($user->job_title_id);
        $this->assertNull($user->whatsapp_number);
        $this->assertNull($user->email);
    }

    public function test_public_asset_request_creates_pengadaan_with_manual_applicant_and_custom_position(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $division = Division::create(['name' => 'HR Department']);
        $attachment = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'applicant_name' => 'Karyawan Baru',
            'whatsapp_number' => '08123456789',
            'email' => 'karyawan@example.com',
            'business_entity_id' => $businessEntity->id,
            'job_title_id' => 'other',
            'custom_job_title' => 'Staff Operasional',
            'division_id' => $division->id,
            'item_name' => 'Meja Kerja',
            'qty' => 1,
            'attachments' => [$attachment],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'name' => 'Karyawan Baru',
            'whatsapp_number' => '08123456789',
            'email' => 'karyawan@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);

        $this->assertDatabaseHas('job_titles', [
            'title' => 'Staff Operasional',
        ]);

        $this->assertDatabaseHas('asset_requests', [
            'item_name' => 'Meja Kerja',
            'division_id' => $division->id,
        ]);
    }

    public function test_public_asset_request_rejects_asset_not_owned_by_applicant(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);
        $otherUser = User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
        $division = Division::create(['name' => 'IT Department']);
        $asset = Asset::create([
            'name' => 'Asus ROG',
            'recipient_id' => $otherUser->id,
        ]);
        $attachment = UploadedFile::fake()->image('receipt.png');

        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'penarikan',
            'user_id' => $user->id,
            'whatsapp_number' => '081234567890',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
            'attachments' => [$attachment],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['asset_id']);
    }

    public function test_public_asset_request_creates_pengadaan_without_attachments(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $division = Division::create(['name' => 'Finance Department']);

        $response = $this->postJson(route('public.asset-requests.store'), [
            'type' => 'pengadaan',
            'applicant_name' => 'Tanpa Lampiran',
            'whatsapp_number' => '08111111111',
            'email' => 'tanpa@example.com',
            'business_entity_id' => $businessEntity->id,
            'job_title_id' => '',
            'division_id' => $division->id,
            'item_name' => 'Mouse Wireless',
            'qty' => 1,
        ]);

        $response->assertStatus(200);

        $request = AssetRequest::first();
        $this->assertSame([], $request->attachment);
    }

    public function test_public_asset_request_cannot_request_locked_asset(): void
    {
        $businessEntity = $this->createBusinessEntity();
        $division = Division::create(['name' => 'IT Department']);
        $user = User::create([
            'name' => 'John Doe',
            'whatsapp_number' => '08123456789',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
        ]);

        $asset = Asset::create([
            'name' => 'Lenovo ThinkPad',
            'serial_number' => 'LNV12345',
            'recipient_id' => $user->id,
        ]);

        // Create an open request for this asset
        AssetRequest::create([
            'type' => 'perbaikan',
            'user_id' => $user->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
            'status' => 'pending',
        ]);

        // Try requesting it again via public route
        $response = $this->actingAs($user)->postJson(route('public.asset-requests.store'), [
            'type' => 'perbaikan',
            'user_id' => $user->id,
            'whatsapp_number' => '08123456789',
            'email' => 'john@example.com',
            'business_entity_id' => $businessEntity->id,
            'division_id' => $division->id,
            'asset_id' => $asset->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['asset_id']);
        $response->assertJsonFragment([
            'errors' => [
                'asset_id' => [
                    "Aset berikut sedang dalam proses pengajuan aktif: {$asset->name}. Silakan tunggu hingga pengajuan sebelumnya selesai ditindaklanjuti.",
                ],
            ],
        ]);
    }

    private function createBusinessEntity(): object
    {
        $id = DB::table('business_entities')->insertGetId([
            'name' => 'PT Complete Solusi Nusantara',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['id' => $id];
    }
}
