<?php

namespace Lathe\Permission\Tests\Fixtures;

/**
 * Lets in only a user who holds the `view reports` permission, read through Spatie's gate hook.
 */
class ReportPolicy
{
    public function viewAny(TestUser $user): bool
    {
        return $user->can('view reports');
    }
}
