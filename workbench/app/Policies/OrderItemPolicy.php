<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\OrderItem;
use Workbench\App\Models\User;

class OrderItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OrderItem $orderItem): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, OrderItem $orderItem): bool
    {
        return true;
    }

    public function delete(User $user, OrderItem $orderItem): bool
    {
        return true;
    }
}
