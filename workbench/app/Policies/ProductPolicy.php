<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Product;
use Workbench\App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Product $product): bool
    {
        return true;
    }

    public function export(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachTags(User $user, Product $product, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function attachAnyTags(User $user, Product $product): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachTags(User $user, Product $product, array $ids): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function addComments(User $user, Product $product): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function import(User $user): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function addCategory(User $user, Product $product): bool
    {
        return true;
    }
}
