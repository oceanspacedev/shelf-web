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
        return $user->can('view_ob::checksheet')
            && ($this->isOwnedBy($user, $obChecksheet) || $this->viewAll($user));
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
        return $user->can('update_ob::checksheet')
            && ($this->isOwnedBy($user, $obChecksheet) || $this->updateAll($user));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ObChecksheet $obChecksheet): bool
    {
        return $user->can('delete_ob::checksheet')
            && ($this->isOwnedBy($user, $obChecksheet) || $this->deleteAll($user));
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

    /**
     * Checksheet petugas OB lain; tanpa izin ini user hanya melihat miliknya.
     */
    public function viewAll(User $user): bool
    {
        return $user->can('view_all_ob::checksheet');
    }

    public function updateAll(User $user): bool
    {
        return $user->can('update_all_ob::checksheet');
    }

    public function deleteAll(User $user): bool
    {
        return $user->can('delete_all_ob::checksheet');
    }

    /**
     * Checksheet tanpa petugas (atau model yang belum tersimpan) cukup dicek izinnya.
     */
    private function isOwnedBy(User $user, ObChecksheet $obChecksheet): bool
    {
        return blank($obChecksheet->user_id) || $obChecksheet->user_id === $user->id;
    }
}
