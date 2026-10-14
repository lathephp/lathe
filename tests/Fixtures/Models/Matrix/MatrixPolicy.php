<?php

namespace Lathe\Tests\Fixtures\Models\Matrix;

use Workbench\App\Models\User;

/**
 * Allows every ability the matrix asks for except the ones a test lists in $denied.
 * Every method is declared, because a missing policy method denies.
 */
class MatrixPolicy
{
    /** @var list<string> */
    public static array $denied = [];

    protected function permits(string $ability): bool
    {
        return ! in_array($ability, self::$denied, true);
    }

    public function viewAny(User $user): bool
    {
        return $this->permits('viewAny');
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->permits('view');
    }

    public function create(User $user): bool
    {
        return $this->permits('create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->permits('update');
    }

    public function addParent(User $user, mixed $model): bool
    {
        return $this->permits('addParent');
    }

    public function addNotable(User $user, mixed $model): bool
    {
        return $this->permits('addNotable');
    }

    public function addDetail(User $user, mixed $model): bool
    {
        return $this->permits('addDetail');
    }

    public function addChildren(User $user, mixed $model): bool
    {
        return $this->permits('addChildren');
    }

    public function addNotes(User $user, mixed $model): bool
    {
        return $this->permits('addNotes');
    }

    public function addPinned(User $user, mixed $model): bool
    {
        return $this->permits('addPinned');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachTags(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('attachTags');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachTags(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('detachTags');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachLabels(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('attachLabels');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachLabels(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('detachLabels');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachParents(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('attachParents');
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function detachParents(User $user, mixed $model, array $ids): bool
    {
        return $this->permits('detachParents');
    }
}
