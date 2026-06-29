<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\AssetRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::request');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('view_asset::request');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_asset::request');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('update_asset::request');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('delete_asset::request');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::request');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('force_delete_asset::request');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_asset::request');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('restore_asset::request');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_asset::request');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AssetRequest $assetRequest): bool
    {
        return $user->can('create_asset::request');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_asset::request');
    }

    /**
     * Determine whether the user can approve the current approval level.
     */
    public function approve(User $user, AssetRequest $assetRequest): bool
    {
        if ($assetRequest->status !== RequestStatus::Pending) {
            return false;
        }

        // Pemohon tidak boleh menyetujui pengajuan sendiri.
        if ($assetRequest->user_id === $user->id) {
            return false;
        }

        $currentApproval = $assetRequest->approvals()
            ->where('level', $assetRequest->current_level)
            ->where('status', 'pending')
            ->first();

        return $currentApproval !== null && $currentApproval->user_id === $user->id;
    }

    /**
     * Determine whether the user can reject the current approval level.
     */
    public function reject(User $user, AssetRequest $assetRequest): bool
    {
        return $this->approve($user, $assetRequest);
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('export_asset::request');
    }
}
