<?php

namespace App\Policies;

use App\Models\AssetService;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetServicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::service') || $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit']);
    }

    public function view(User $user, AssetService $assetService): bool
    {
        if (! $user->can('view_asset::service') && ! $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            return true;
        }

        return $assetService->created_by === $user->id || $assetService->serviced_by_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('create_asset::service') || $user->hasRole(['super_admin', 'admin', 'general_affair']);
    }

    public function update(User $user, AssetService $assetService): bool
    {
        if (! $user->can('update_asset::service') && ! $user->hasRole(['super_admin', 'admin', 'general_affair'])) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin', 'general_affair'])) {
            return true;
        }

        return $assetService->created_by === $user->id || $assetService->serviced_by_user_id === $user->id;
    }

    public function delete(User $user, AssetService $assetService): bool
    {
        if (! $user->can('delete_asset::service') && ! $user->hasRole(['super_admin', 'admin'])) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $assetService->created_by === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::service') || $user->hasRole(['super_admin', 'admin']);
    }

    public function export(User $user): bool
    {
        return $user->can('export_asset::service') || $user->hasRole(['super_admin', 'admin', 'general_affair', 'audit']);
    }
}
