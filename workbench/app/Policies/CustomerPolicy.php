<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Customer;
use Workbench\App\Models\User;

class CustomerPolicy
{
    // Support works orders and may open one order's customer, but not list every customer.
    public function viewAny(User $user): bool
    {
        return $user->email !== 'support@lathephp.com';
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Customer $customer): bool
    {
        return true;
    }

    public function inlineEdit(User $user, Customer $customer): bool
    {
        return true;
    }

    public function addOrders(User $user, Customer $customer): bool
    {
        return $user->email === 'admin@lathephp.com';
    }
}
