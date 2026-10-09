<?php

namespace App\Policies;

use App\Models\ObTaskTemplate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ObTaskTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_ob::task::template');
    }

    public function view(User $user, ObTaskTemplate $template): bool
    {
        return $user->can('view_ob::task::template');
    }

    public function create(User $user): bool
    {
        return $user->can('create_ob::task::template');
    }

    public function update(User $user, ObTaskTemplate $template): bool
    {
        return $user->can('update_ob::task::template');
    }

    public function delete(User $user, ObTaskTemplate $template): bool
    {
        return $user->can('delete_ob::task::template');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_ob::task::template');
    }
}
