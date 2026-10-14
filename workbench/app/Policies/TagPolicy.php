<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Tag;
use Workbench\App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tag $tag): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Tag $tag): bool
    {
        return true;
    }

    public function delete(User $user, Tag $tag): bool
    {
        return true;
    }
}
