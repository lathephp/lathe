<?php

namespace Workbench\App\Policies;

use Lathe\Actions\Action;
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

    public function viewWidget(User $user, string $widget): bool
    {
        return true;
    }

    public function viewLens(User $user, string $lens): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function addCustomer(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function addItems(User $user, Order $order): bool
    {
        return $user->email === 'admin@lathephp.com';
    }

    public function export(User $user): bool
    {
        return true;
    }

    public function runAction(User $user, Order $order, Action $action): bool
    {
        return $this->update($user, $order);
    }

    public function runDestructiveAction(User $user, Order $order, Action $action): bool
    {
        return $user->email === 'admin@lathephp.com';
    }
}
