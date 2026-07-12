<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VehicleChecksheet;
use Illuminate\Auth\Access\HandlesAuthorization;

class VehicleChecksheetPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_vehicle::checksheet');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('view_vehicle::checksheet');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_vehicle::checksheet');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('update_vehicle::checksheet');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('delete_vehicle::checksheet');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_vehicle::checksheet');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('force_delete_vehicle::checksheet');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_vehicle::checksheet');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('restore_vehicle::checksheet');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_vehicle::checksheet');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, VehicleChecksheet $vehicleChecksheet): bool
    {
        return $user->can('replicate_vehicle::checksheet');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_vehicle::checksheet');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('export_vehicle::checksheet');
    }

    /**
     * Determine whether the user can import.
     */
    public function import(User $user): bool
    {
        return $user->can('import_vehicle::checksheet');
    }
}
