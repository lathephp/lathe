<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Collection;
use Workbench\App\Models\User;

class CollectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $collection): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Collection $collection): bool
    {
        return true;
    }

    public function delete(User $user, Collection $collection): bool
    {
        return true;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachProducts(User $user, Collection $collection, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function attachAnyProducts(User $user, Collection $collection): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachProducts(User $user, Collection $collection, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }
}
