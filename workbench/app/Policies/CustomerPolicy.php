<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Customer;
use Workbench\App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Customer $customer): bool
    {
        return true;
    }
}
