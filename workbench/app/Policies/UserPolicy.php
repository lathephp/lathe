<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\User;

/**
 * Every panel user may manage users in the workbench; the panel gate decides who is a panel user.
 * The live demo prints its sign-in, so there no one may change or delete the seeded accounts.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $model): bool
    {
        return ! $this->seededDemoAccount($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isNot($model) && ! $this->seededDemoAccount($model);
    }

    /**
     * One of the live demo's sign-ins, which every visitor shares until the nightly reset.
     * The flag is read from the environment, as WorkbenchServiceProvider reads it.
     */
    protected function seededDemoAccount(User $model): bool
    {
        return getenv('LATHE_DEMO') === 'true'
            && in_array($model->email, ['admin@lathephp.com', 'editor@lathephp.com', 'guest@lathephp.com'], true);
    }
}
