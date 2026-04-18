<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\PublicAssetRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublicAssetRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_public::asset::request');
    }

    public function view(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return $user->can('view_public::asset::request');
    }

    public function create(User $user): bool
    {
        return $user->can('create_public::asset::request');
    }

    public function update(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return $user->can('update_public::asset::request');
    }

    public function delete(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return $user->can('delete_public::asset::request')
            && $this->canMutateRecord($publicAssetRequest);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_public::asset::request');
    }

    public function forceDelete(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return $user->can('delete_public::asset::request')
            && $this->canMutateRecord($publicAssetRequest);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('delete_any_public::asset::request');
    }

    public function restore(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return $user->can('delete_public::asset::request')
            && $this->canMutateRecord($publicAssetRequest);
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('delete_any_public::asset::request');
    }

    public function replicate(User $user, PublicAssetRequest $publicAssetRequest): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    private function canMutateRecord(PublicAssetRequest $publicAssetRequest): bool
    {
        return $publicAssetRequest->status === RequestStatus::Pending;
    }
}
