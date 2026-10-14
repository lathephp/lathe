<?php

namespace Lathe\Permission\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

/**
 * Lets every user who may enter the panel manage permissions. Registered only by
 * PermissionPlugin::withDefaultPolicies(), which logs a warning for it.
 */
class PermissionPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows('viewLathe');
    }

    public function view(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function delete(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function restore(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }

    public function forceDelete(Authenticatable $user): bool
    {
        return $this->viewAny($user);
    }
}
