<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Filament\Resources\PublicAssetRequestResource;
use App\Mail\ApprovalRequested;
use App\Mail\PublicAssetRequestStatusChanged;
use App\Mail\PublicAssetRequestSubmitted;
use App\Models\ApprovalLevel;
use App\Models\PublicAssetRequest;
use App\Models\RequestApproval;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class PublicAssetRequestApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_shows_selectable_divisions_from_configured_options(): void
    {
        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'HR',
            'level' => 1,
            'approver_name' => 'HR Manager',
            'approver_email' => 'hr.manager@example.com',
        ]);

        $response = $this->get(route('asset-requests.create', 'pengadaan-aset'));

        $response->assertOk();
        $response->assertSee('list="division-options"', false);
        $response->assertSee('Finance');
        $response->assertSee('HR');
    }

    public function test_create_page_does_not_expose_internal_user_directory_data(): void
    {
        User::factory()->create([
            'name' => 'Internal Employee',
            'email' => 'internal.employee@example.com',
        ]);

        $response = $this->get(route('asset-requests.create', 'perbaikan-aset'));

        $response->assertOk();
        $response->assertDontSee('Internal Employee');
        $response->assertDontSee('user-names', false);
        $response->assertDontSee('userAssetsMapping', false);
        $response->assertDontSee('item-names', false);
    }

    public function test_create_page_only_lists_configured_divisions(): void
    {
        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        PublicAssetRequest::create([
            'uuid' => '4b9af85e-35e2-40f7-96c8-9df2169d2f59',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Historical Request',
            'email' => 'history@example.com',
            'division' => 'Injected Division',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $response = $this->get(route('asset-requests.create', 'pengadaan-aset'));

        $response->assertOk();
        $response->assertSee('Finance');
        $response->assertDontSee('Injected Division');
    }

    public function test_it_throws_validation_error_before_duplicate_approval_level_hits_db_constraint(): void
    {
        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'IT',
            'level' => 1,
            'approver_name' => 'IT Manager',
            'approver_email' => 'it.manager@example.com',
        ]);

        try {
            ApprovalLevel::create([
                'request_type' => 'pengadaan_aset',
                'division' => 'it',
                'level' => 1,
                'approver_name' => 'IT Supervisor',
                'approver_email' => 'it.supervisor@example.com',
            ]);

            $this->fail('Expected duplicate approval configuration to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Konfigurasi approval untuk divisi it di level 1 sudah ada.',
                $exception->errors()['level'][0],
            );
        }

        $this->assertDatabaseCount('approval_levels', 1);
    }

    public function test_it_stores_a_single_pdf_attachment_under_one_megabyte(): void
    {
        Storage::fake('public');
        Mail::fake();

        $response = $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'attachment' => UploadedFile::fake()->create('lampiran.pdf', 900, 'application/pdf'),
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $response->assertRedirect(route('asset-requests.success', $assetRequest->uuid));
        $this->assertSame('lampiran.pdf', $assetRequest->attachment_original_name);
        $this->assertSame(
            "public-asset-requests/attachments/{$assetRequest->id}.pdf",
            $assetRequest->attachment_path,
        );
        Storage::disk('public')->assertExists($assetRequest->attachment_path);
    }

    public function test_it_rejects_attachment_larger_than_one_megabyte(): void
    {
        Storage::fake('public');
        Mail::fake();

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'attachment' => UploadedFile::fake()->create('besar.pdf', 1025, 'application/pdf'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('public_asset_requests', 0);
    }

    public function test_it_rejects_non_pdf_and_non_image_attachment(): void
    {
        Storage::fake('public');
        Mail::fake();

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'attachment' => UploadedFile::fake()->create('script.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('attachment');

        $this->assertDatabaseCount('public_asset_requests', 0);
    }

    public function test_it_rejects_unconfigured_divisions_when_specific_tracks_exist(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Legal',
            'email' => 'legal@example.com',
            'division' => 'Legal',
            'placement' => 'Jakarta',
            'item_name' => 'Monitor',
            'qty' => 1,
        ])->assertSessionHasErrors('division');

        $this->assertDatabaseCount('public_asset_requests', 0);
        $this->assertDatabaseCount('request_approvals', 0);
    }

    public function test_it_uses_division_specific_approval_levels(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => ApprovalLevel::ALL_DIVISIONS,
            'level' => 1,
            'approver_name' => 'Global Approval',
            'approver_email' => 'global@example.com',
        ]);

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        $response = $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();
        $approvals = RequestApproval::query()
            ->orderBy('level')
            ->get();
        $firstApproval = $approvals->first();

        $response->assertRedirect(route('asset-requests.success', $assetRequest->uuid));
        $this->assertSame(RequestStatus::Pending, $assetRequest->status);
        $this->assertSame('Finance', $assetRequest->approval_track);
        $this->assertNotNull($firstApproval);
        $this->assertSame('finance.manager@example.com', $firstApproval->approver_email);
        $this->assertCount(2, $approvals);
        $this->assertSame('finance.director@example.com', $approvals[1]->approver_email);

        Mail::assertSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('finance.manager@example.com'));
        Mail::assertNotSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('global@example.com'));

        $this->post(route('asset-requests.process-approval', $firstApproval->token), [
            'action' => 'approve',
            'notes' => 'OK',
        ])->assertRedirect(route('asset-requests.show-approval', $firstApproval->token));

        $this->assertDatabaseCount('request_approvals', 2);
        $this->assertDatabaseHas('request_approvals', [
            'public_asset_request_id' => $assetRequest->id,
            'approver_email' => 'finance.director@example.com',
            'level' => 2,
        ]);
        Mail::assertSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('finance.director@example.com'));
    }

    public function test_it_matches_divisions_case_insensitively(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $this->assertDatabaseHas('request_approvals', [
            'approver_email' => 'finance.manager@example.com',
            'level' => 1,
        ]);
    }

    public function test_it_falls_back_to_global_approval_levels_when_division_has_no_specific_config(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => ApprovalLevel::ALL_DIVISIONS,
            'level' => 1,
            'approver_name' => 'Global Approval',
            'approver_email' => 'global@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Legal',
            'email' => 'legal@example.com',
            'division' => 'Legal',
            'placement' => 'Jakarta',
            'item_name' => 'Monitor',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $this->assertSame(RequestStatus::Pending, $assetRequest->status);
        $this->assertSame(ApprovalLevel::ALL_DIVISIONS, $assetRequest->approval_track);
        $this->assertDatabaseHas('request_approvals', [
            'public_asset_request_id' => $assetRequest->id,
            'approver_email' => 'global@example.com',
            'level' => 1,
        ]);
    }

    public function test_it_allows_unlisted_divisions_when_global_track_exists_alongside_specific_tracks(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => ApprovalLevel::ALL_DIVISIONS,
            'level' => 1,
            'approver_name' => 'Global Approval',
            'approver_email' => 'global@example.com',
        ]);

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $response = $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Legal',
            'email' => 'legal@example.com',
            'division' => 'Legal',
            'placement' => 'Jakarta',
            'item_name' => 'Monitor',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $response->assertRedirect(route('asset-requests.success', $assetRequest->uuid));
        $this->assertSame(RequestStatus::Pending, $assetRequest->status);
        $this->assertSame(ApprovalLevel::ALL_DIVISIONS, $assetRequest->approval_track);
        $this->assertDatabaseHas('request_approvals', [
            'public_asset_request_id' => $assetRequest->id,
            'approver_email' => 'global@example.com',
            'level' => 1,
        ]);
    }

    public function test_it_auto_approves_when_no_approval_is_configured(): void
    {
        Mail::fake();

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon HR',
            'email' => 'hr@example.com',
            'division' => 'HR',
            'placement' => 'Bandung',
            'item_name' => 'Printer',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $this->assertSame(RequestStatus::Approved, $assetRequest->status);
        $this->assertDatabaseCount('request_approvals', 0);
        $this->assertSame(
            'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
            $assetRequest->admin_notes,
        );

        Mail::assertSent(PublicAssetRequestSubmitted::class, fn (PublicAssetRequestSubmitted $mail) => $mail->hasTo('hr@example.com'));
        Mail::assertSent(PublicAssetRequestStatusChanged::class, fn (PublicAssetRequestStatusChanged $mail) => $mail->hasTo('hr@example.com'));
    }

    public function test_it_does_not_persist_request_when_initial_approval_notification_fails(): void
    {
        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('finance.manager@example.com')
            ->andReturn(new class
            {
                public function send($mailable): void
                {
                    throw new RuntimeException('Mail transport down');
                }

                public function queue($mailable): void
                {
                    throw new RuntimeException('Mail transport down');
                }
            });

        $response = $this->from(route('asset-requests.create', 'pengadaan-aset'))
            ->post(route('asset-requests.store', 'pengadaan-aset'), [
                'requester_name' => 'Pemohon Finance',
                'email' => 'pemohon@example.com',
                'division' => 'Finance',
                'placement' => 'Jakarta',
                'item_name' => 'Laptop',
                'qty' => 1,
            ]);

        $response->assertRedirect(route('asset-requests.create', 'pengadaan-aset'));
        $response->assertSessionHasErrors('request');
        $this->assertDatabaseCount('public_asset_requests', 0);
        $this->assertDatabaseCount('request_approvals', 0);
    }

    public function test_it_rejects_stale_public_approval_links_for_terminal_requests(): void
    {
        Mail::fake();

        $assetRequest = PublicAssetRequest::create([
            'uuid' => '9efca4d7-063c-4f07-a209-0dd1935ace22',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Rejected,
        ]);

        $approvalLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $approval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => '0215aa89-91f5-4fcf-aaca-e732aa8916eb',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $this->post(route('asset-requests.process-approval', $approval->token), [
            'action' => 'approve',
        ])->assertRedirect(route('asset-requests.show-approval', $approval->token));

        $this->assertDatabaseHas('request_approvals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_keeps_current_approval_pending_when_next_approval_notification_fails(): void
    {
        $assetRequest = PublicAssetRequest::create([
            'uuid' => '8120d598-dfe0-49df-ad45-3539ef86e427',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $firstLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $secondLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        $firstApproval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $firstLevel->id,
            'token' => '6bec2bd0-a748-4f3f-a336-5349f1b67d43',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $secondApproval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $secondLevel->id,
            'token' => '4d455b16-94b8-4736-9d4f-d21b980dbf78',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->with('finance.director@example.com')
            ->andReturn(new class
            {
                public function send($mailable): void
                {
                    throw new RuntimeException('Mail transport down');
                }

                public function queue($mailable): void
                {
                    throw new RuntimeException('Mail transport down');
                }
            });

        $response = $this->post(route('asset-requests.process-approval', $firstApproval->token), [
            'action' => 'approve',
            'notes' => 'OK',
        ]);

        $response->assertRedirect(route('asset-requests.show-approval', $firstApproval->token));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('request_approvals', [
            'id' => $firstApproval->id,
            'status' => 'pending',
            'responded_at' => null,
        ]);
        $this->assertDatabaseHas('request_approvals', [
            'id' => $secondApproval->id,
            'status' => 'pending',
        ]);

        $assetRequest->refresh();
        $this->assertSame(RequestStatus::Pending, $assetRequest->status);
    }

    public function test_approval_page_renders_error_and_info_flash_messages(): void
    {
        $assetRequest = PublicAssetRequest::create([
            'uuid' => 'd5d64e3c-b0a7-46d4-a8ef-5bec75b38bc6',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $approvalLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $approval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => '1b79d856-d3a0-49a9-adfb-28c9129b2663',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $this->withSession([
            'error' => 'Pengajuan tidak dapat diproses saat ini.',
            'info' => 'Pengajuan ini masih menunggu approval level sebelumnya.',
        ])
            ->get(route('asset-requests.show-approval', $approval->token))
            ->assertOk()
            ->assertSee('Pengajuan tidak dapat diproses saat ini.')
            ->assertSee('Pengajuan ini masih menunggu approval level sebelumnya.');
    }

    public function test_it_blocks_out_of_order_public_approval_links(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $secondApproval = RequestApproval::query()
            ->where('level', 2)
            ->firstOrFail();

        $this->get(route('asset-requests.show-approval', $secondApproval->token))
            ->assertOk()
            ->assertSee('Pengajuan ini masih menunggu approval level sebelumnya.');

        $this->post(route('asset-requests.process-approval', $secondApproval->token), [
            'action' => 'approve',
        ])->assertRedirect(route('asset-requests.show-approval', $secondApproval->token));

        $this->assertDatabaseHas('request_approvals', [
            'id' => $secondApproval->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_does_not_flash_success_after_public_approval_response(): void
    {
        Mail::fake();

        $assetRequest = PublicAssetRequest::create([
            'uuid' => 'd031fd88-a8d7-4031-a0b3-b6d7e88b2bb2',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $approvalLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $approval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => 'd86ffeb7-5f79-4354-a15f-8f026f44c7f4',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $response = $this->post(route('asset-requests.process-approval', $approval->token), [
            'action' => 'reject',
            'notes' => 'Tidak sesuai kebutuhan',
        ]);

        $response->assertRedirect(route('asset-requests.show-approval', $approval->token));
        $response->assertSessionMissing('success');
    }

    public function test_filament_resource_only_allows_deleting_pending_requests(): void
    {
        $pendingRequest = PublicAssetRequest::create([
            'uuid' => '1fe0e575-c1c4-4bbb-a5cb-b950e80962e1',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pending Request',
            'email' => 'pending@example.com',
            'division' => 'IT',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $approvedRequest = PublicAssetRequest::create([
            'uuid' => '2e98eebc-28a7-42a9-a45b-1d6d899517ab',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Approved Request',
            'email' => 'approved@example.com',
            'division' => 'IT',
            'placement' => 'Jakarta',
            'item_name' => 'Monitor',
            'qty' => 1,
            'status' => RequestStatus::Approved,
        ]);

        $rejectedRequest = PublicAssetRequest::create([
            'uuid' => '5d972179-46d7-4576-992e-1d399eb95b8c',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Rejected Request',
            'email' => 'rejected@example.com',
            'division' => 'IT',
            'placement' => 'Jakarta',
            'item_name' => 'Printer',
            'qty' => 1,
            'status' => RequestStatus::Rejected,
        ]);

        $this->assertTrue(PublicAssetRequestResource::canDeleteRecord($pendingRequest));
        $this->assertFalse(PublicAssetRequestResource::canDeleteRecord($approvedRequest));
        $this->assertFalse(PublicAssetRequestResource::canDeleteRecord($rejectedRequest));
    }

    public function test_it_soft_deletes_request_without_removing_attachment(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('public-asset-requests/attachments/lampiran.pdf', 'dummy');

        $assetRequest = PublicAssetRequest::create([
            'uuid' => 'e15b7411-10b8-40c0-8a96-02f83377b93b',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'attachment_path' => 'public-asset-requests/attachments/lampiran.pdf',
            'attachment_original_name' => 'lampiran.pdf',
            'status' => RequestStatus::Pending,
        ]);

        $assetRequest->delete();

        $this->assertSoftDeleted($assetRequest);
        Storage::disk('public')->assertExists('public-asset-requests/attachments/lampiran.pdf');
    }

    public function test_it_force_deletes_request_and_removes_attachment(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('public-asset-requests/attachments/lampiran.pdf', 'dummy');

        $assetRequest = PublicAssetRequest::create([
            'uuid' => 'ab287df7-986f-4f1c-abf1-ec95fa35de6d',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'attachment_path' => 'public-asset-requests/attachments/lampiran.pdf',
            'attachment_original_name' => 'lampiran.pdf',
            'status' => RequestStatus::Pending,
        ]);

        $assetRequest->forceDelete();

        $this->assertDatabaseMissing('public_asset_requests', [
            'id' => $assetRequest->id,
        ]);
        Storage::disk('public')->assertMissing('public-asset-requests/attachments/lampiran.pdf');
    }

    public function test_it_blocks_public_approval_links_for_soft_deleted_requests(): void
    {
        Mail::fake();

        $assetRequest = PublicAssetRequest::create([
            'uuid' => '8c68f374-b444-4df5-94f0-a8af50abde89',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $approvalLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $approval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => '36d7a447-05a8-45f4-bb72-2ddfe84be1cb',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $assetRequest->delete();

        $this->get(route('asset-requests.show-approval', $approval->token))
            ->assertOk()
            ->assertSee('Link approval ini tidak lagi aktif.');

        $this->post(route('asset-requests.process-approval', $approval->token), [
            'action' => 'approve',
        ])->assertRedirect(route('asset-requests.show-approval', $approval->token));

        $this->assertDatabaseHas('request_approvals', [
            'id' => $approval->id,
            'status' => 'pending',
        ]);
    }

    public function test_it_keeps_snapshot_of_future_approvers_after_configuration_changes(): void
    {
        Mail::fake();

        $firstLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $secondLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $firstApproval = RequestApproval::query()
            ->where('level', 1)
            ->firstOrFail();
        $secondApproval = RequestApproval::query()
            ->where('level', 2)
            ->firstOrFail();

        $firstLevel->update(['division' => 'Operations']);
        $secondLevel->update([
            'approver_name' => 'Updated Director',
            'approver_email' => 'updated.director@example.com',
        ]);

        $this->post(route('asset-requests.process-approval', $firstApproval->token), [
            'action' => 'approve',
            'notes' => 'OK',
        ])->assertRedirect(route('asset-requests.show-approval', $firstApproval->token));

        $secondApproval->refresh();

        $this->assertSame('finance.director@example.com', $secondApproval->approver_email);
        Mail::assertSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('finance.director@example.com'));
        Mail::assertNotSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('updated.director@example.com'));
    }

    public function test_it_preserves_request_approvals_when_approval_levels_are_deleted(): void
    {
        Mail::fake();

        ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $secondLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 2,
            'approver_name' => 'Finance Director',
            'approver_email' => 'finance.director@example.com',
        ]);

        $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $firstApproval = RequestApproval::query()
            ->where('level', 1)
            ->firstOrFail();
        $secondApproval = RequestApproval::query()
            ->where('level', 2)
            ->firstOrFail();

        $secondLevel->delete();
        $secondApproval->refresh();

        $this->assertNull($secondApproval->approval_level_id);

        $this->post(route('asset-requests.process-approval', $firstApproval->token), [
            'action' => 'approve',
            'notes' => 'OK',
        ])->assertRedirect(route('asset-requests.show-approval', $firstApproval->token));

        $this->assertDatabaseHas('request_approvals', [
            'id' => $secondApproval->id,
            'approver_email' => 'finance.director@example.com',
            'status' => 'pending',
        ]);
        Mail::assertSent(ApprovalRequested::class, fn (ApprovalRequested $mail) => $mail->hasTo('finance.director@example.com'));
    }

    public function test_it_still_creates_request_when_mail_delivery_fails(): void
    {
        Mail::shouldReceive('to')
            ->twice()
            ->with('pemohon@example.com')
            ->andReturn(new class
            {
                public function send($mailable): void
                {
                    throw new RuntimeException('Mail transport down');
                }
            });

        $response = $this->post(route('asset-requests.store', 'pengadaan-aset'), [
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $response->assertRedirect(route('asset-requests.success', $assetRequest->uuid));
        $this->assertSame(RequestStatus::Approved, $assetRequest->status);
        $this->assertSame(
            'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
            $assetRequest->admin_notes,
        );

        $this->assertDatabaseCount('public_asset_requests', 1);
        $this->assertDatabaseCount('request_approvals', 0);
    }

    public function test_legacy_public_asset_request_post_route_still_submits_form_data(): void
    {
        Mail::fake();

        $response = $this->post('/public-asset-requests/pengadaan-aset', [
            'requester_name' => 'Pemohon Legal',
            'email' => 'legal@example.com',
            'division' => 'Legal',
            'placement' => 'Jakarta',
            'item_name' => 'Monitor',
            'qty' => 1,
        ]);

        $assetRequest = PublicAssetRequest::query()->firstOrFail();

        $response->assertRedirect(route('asset-requests.success', $assetRequest->uuid));
        $this->assertSame('Legal', $assetRequest->division);
    }

    public function test_legacy_public_approval_post_route_still_processes_approval(): void
    {
        Mail::fake();

        $assetRequest = PublicAssetRequest::create([
            'uuid' => '3bbd0268-e2e1-4c96-9c4a-0bbf83c89cb7',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Pemohon Finance',
            'email' => 'pemohon@example.com',
            'division' => 'Finance',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
        ]);

        $approvalLevel = ApprovalLevel::create([
            'request_type' => 'pengadaan_aset',
            'division' => 'Finance',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $approval = RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => '3887400b-f680-4be6-a9b5-67c1af6c3f19',
            'level' => 1,
            'approver_name' => 'Finance Manager',
            'approver_email' => 'finance.manager@example.com',
        ]);

        $response = $this->post("/public-asset-requests/approve/{$approval->token}", [
            'action' => 'approve',
            'notes' => 'OK',
        ]);

        $response->assertRedirect(route('asset-requests.show-approval', $approval->token));
        $this->assertDatabaseHas('request_approvals', [
            'id' => $approval->id,
            'status' => 'approved',
        ]);
        $assetRequest->refresh();
        $this->assertSame(RequestStatus::Approved, $assetRequest->status);
    }

    public function test_backfill_auto_approves_historical_pending_requests_without_an_approval_track(): void
    {
        $assetRequest = PublicAssetRequest::create([
            'uuid' => 'a8c0be1e-cd2f-4f1e-9c8e-d2d24993de0e',
            'request_type' => 'pengadaan_aset',
            'requester_name' => 'Historical Request',
            'email' => 'historical@example.com',
            'division' => 'Unconfigured Division',
            'placement' => 'Jakarta',
            'item_name' => 'Laptop',
            'qty' => 1,
            'status' => RequestStatus::Pending,
            'approval_track' => null,
        ]);

        $migration = require database_path('migrations/2026_03_10_130000_freeze_public_asset_request_approval_flow.php');

        $backfill = \Closure::bind(function () {
            $this->backfillApprovalRouting();
        }, $migration, $migration);

        $backfill();

        $assetRequest->refresh();

        $this->assertSame(RequestStatus::Approved, $assetRequest->status);
        $this->assertNull($assetRequest->approval_track);
        $this->assertSame(
            'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
            $assetRequest->admin_notes,
        );
        $this->assertDatabaseCount('request_approvals', 0);
    }
}
