<?php

namespace Lathe\Permission\Tests\Fixtures;

/**
 * A host app's own Permission policy, registered in place of the shipped one.
 */
class AppPermissionPolicy
{
    public function viewAny(): bool
    {
        return true;
    }
}
