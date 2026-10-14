<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Workbench\App\Models\User;

/**
 * The grandchildren's own viewAny, so a test can close the through lists without closing
 * the parent Resource that holds them.
 */
class MatrixGrandchildPolicy extends MatrixPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->permits('viewAnyGrandchildren');
    }
}
