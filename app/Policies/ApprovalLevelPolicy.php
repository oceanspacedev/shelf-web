<?php

namespace App\Policies;

use App\Models\ApprovalLevel;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApprovalLevelPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function view(User $user, ApprovalLevel $approvalLevel): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function update(User $user, ApprovalLevel $approvalLevel): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function delete(User $user, ApprovalLevel $approvalLevel): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function forceDelete(User $user, ApprovalLevel $approvalLevel): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function restore(User $user, ApprovalLevel $approvalLevel): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->canManageApprovalLevels($user);
    }

    public function replicate(User $user, ApprovalLevel $approvalLevel): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function canManageApprovalLevels(User $user): bool
    {
        return $user->hasRole('super_admin');
    }
}
