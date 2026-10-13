<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Order;
use Workbench\App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return true;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function inlineEdit(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function replicate(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function viewLens(User $user, string $lens): bool
    {
        return true;
    }
}
