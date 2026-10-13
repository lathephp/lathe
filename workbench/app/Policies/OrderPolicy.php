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

    public function delete(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }
}
