<?php

namespace App\Policies;

use App\Models\AssetTransfer;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetTransferPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::transfer');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('view_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_asset::transfer');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('update_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('delete_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::transfer');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('force_delete_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_asset::transfer');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('restore_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_asset::transfer');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, AssetTransfer $assetTransfer): bool
    {
        return $user->can('replicate_asset::transfer') && $this->withinBusinessEntityScope($user, $assetTransfer);
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_asset::transfer');
    }

    /**
     * Determine whether the user can export.
     */
    public function export(User $user): bool
    {
        return $user->can('export_asset::transfer');
    }

    /**
     * Determine whether the user can make stock BAs (Serah Terima,
     * Pengembalian) on behalf of any general affair staff member.
     */
    public function manageStock(User $user): bool
    {
        return $user->can('manage_stock_asset::transfer');
    }

    /**
     * Ubah jenis BA, pihak, dan aset pada BA yang sudah dibuat.
     */
    public function updateCore(User $user): bool
    {
        return $user->can('update_core_asset::transfer');
    }

    /**
     * Lepas satu riwayat transfer dari aset (BA utamanya tetap ada).
     */
    public function deleteDetail(User $user): bool
    {
        return $user->can('delete_detail_asset::transfer');
    }

    /**
     * Record di luar badan usaha yang bisa diakses user ditolak (lihat
     * AssetTransfer::isAccessibleBy). Model yang belum tersimpan, yaitu cek
     * izin saja, tidak dibatasi.
     */
    private function withinBusinessEntityScope(User $user, AssetTransfer $assetTransfer): bool
    {
        return ! $assetTransfer->exists || $assetTransfer->isAccessibleBy($user);
    }
}
