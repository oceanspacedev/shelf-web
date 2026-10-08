<?php

namespace App\Policies;

use App\Models\AssetQrLabelHistory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetQrLabelHistoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_asset::qr::label::history');
    }

    public function view(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('view_asset::qr::label::history');
    }

    public function create(User $user): bool
    {
        return $user->can('create_asset::qr::label::history');
    }

    public function update(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('update_asset::qr::label::history');
    }

    public function delete(User $user, AssetQrLabelHistory $assetQrLabelHistory): bool
    {
        return $user->can('delete_asset::qr::label::history');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_asset::qr::label::history');
    }
}
