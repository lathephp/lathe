<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return true;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return true;
    }

    /**
     * Merge Tags, and the pivot actions of a product's Tags panel.
     */
    public function runAction(User $user, Tag $tag): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachProducts(User $user, Tag $tag, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function attachAnyProducts(User $user, Tag $tag): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachProducts(User $user, Tag $tag, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }
}
