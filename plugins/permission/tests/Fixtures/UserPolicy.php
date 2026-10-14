<?php

namespace Lathe\Permission\Tests\Fixtures;

/**
 * The host app's User policy, letting the admin list users and assign their roles.
 */
class UserPolicy
{
    public function viewAny(): bool
    {
        return true;
    }

    public function view(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return true;
    }
}
