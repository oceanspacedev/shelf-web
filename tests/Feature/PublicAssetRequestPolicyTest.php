<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\PublicAssetRequest;
use App\Models\User;
use App\Policies\PublicAssetRequestPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class PublicAssetRequestPolicyTest extends TestCase
{
    public function test_public_asset_request_policy_is_discovered(): void
    {
        $policy = Gate::getPolicyFor(PublicAssetRequest::class);

        $this->assertInstanceOf(PublicAssetRequestPolicy::class, $policy);
    }

    public function test_public_asset_request_policy_only_allows_deleting_pending_records(): void
    {
        $policy = new PublicAssetRequestPolicy();

        $user = $this->createPartialMock(User::class, ['can']);
        $user->expects($this->exactly(2))
            ->method('can')
            ->with('delete_public::asset::request')
            ->willReturn(true);

        $pendingRequest = new PublicAssetRequest([
            'status' => RequestStatus::Pending,
        ]);

        $approvedRequest = new PublicAssetRequest([
            'status' => RequestStatus::Approved,
        ]);

        $this->assertTrue($policy->delete($user, $pendingRequest));
        $this->assertFalse($policy->delete($user, $approvedRequest));
    }
}
