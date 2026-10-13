<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\User;

/**
 * Every panel user may manage users in the workbench; the panel gate decides who is a panel user.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $model): bool
    {
        return true;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isNot($model);
    }
}
