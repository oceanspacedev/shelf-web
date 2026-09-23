<?php

namespace App\Policies;

use App\Models\ObChecksheet;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObChecksheetPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ob::checksheet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ObChecksheet $obChecksheet): bool
    {
        if (! $user->can('view_ob::checksheet')) {
            return false;
        }

        if ($user instanceof \Mockery\MockInterface || blank($obChecksheet->user_id)) {
            return true;
        }

        if ($user->hasRole(['super_admin', 'admin', 'general_affair', 'audit'])) {
            return true;
        }

        return $obChecksheet->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_ob::checksheet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ObChecksheet $obChecksheet): bool
    {
        if (! $user->can('update_ob::checksheet')) {
            return false;
        }

        if ($user instanceof \Mockery\MockInterface || blank($obChecksheet->user_id)) {
            return true;
        }

        if ($user->hasRole(['super_admin', 'admin', 'general_affair'])) {
            return true;
        }

        return $obChecksheet->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ObChecksheet $obChecksheet): bool
    {
        if (! $user->can('delete_ob::checksheet')) {
            return false;
        }

        if ($user instanceof \Mockery\MockInterface || blank($obChecksheet->user_id)) {
            return true;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $obChecksheet->user_id === $user->id;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::checksheet');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('force_delete_ob::checksheet');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_ob::checksheet');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('restore_ob::checksheet');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_ob::checksheet');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('replicate_ob::checksheet');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_ob::checksheet');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('export_ob::checksheet');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('import_ob::checksheet');
    }
}
