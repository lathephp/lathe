<?php

namespace Workbench\App\Policies;

use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;
use Workbench\App\Models\User;

/**
 * Whoever may open the panel may read the audit log. Nobody writes to it.
 */
class ActionEventPolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewLathe');
    }

    public function view(User $user, ActionEvent $event): bool
    {
        return $this->viewAny($user);
    }
}
