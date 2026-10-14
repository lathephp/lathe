<?php

namespace Lathe\Permission\Tests\Fixtures;

/**
 * A host app's own Role policy, registered in place of the shipped one.
 */
class AppRolePolicy
{
    public function viewAny(): bool
    {
        return true;
    }
}
