<?php

namespace Tests\Feature;

use App\Models\ApprovalLevel;
use App\Models\User;
use App\Policies\ApprovalLevelPolicy;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ApprovalLevelPolicyTest extends TestCase
{
    public function test_approval_level_policy_is_discovered(): void
    {
        $policy = Gate::getPolicyFor(ApprovalLevel::class);

        $this->assertInstanceOf(ApprovalLevelPolicy::class, $policy);
    }

    public function test_approval_level_policy_only_allows_super_admins(): void
    {
        $policy = new ApprovalLevelPolicy();
        $approvalLevel = new ApprovalLevel();

        $superAdmin = $this->createPartialMock(User::class, ['hasRole']);
        $superAdmin->expects($this->once())
            ->method('hasRole')
            ->with('super_admin')
            ->willReturn(true);

        $regularUser = $this->createPartialMock(User::class, ['hasRole']);
        $regularUser->expects($this->once())
            ->method('hasRole')
            ->with('super_admin')
            ->willReturn(false);

        $this->assertTrue($policy->update($superAdmin, $approvalLevel));
        $this->assertFalse($policy->update($regularUser, $approvalLevel));
    }
}
