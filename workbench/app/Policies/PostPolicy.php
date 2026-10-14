<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Post;
use Workbench\App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Post $post): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return true;
    }

    public function inlineEdit(User $user, Post $post): bool
    {
        return true;
    }

    // Nobody deletes a post from the panel, so the demo's trash holds only the seeded posts.
    public function delete(User $user, Post $post): bool
    {
        return false;
    }

    public function restore(User $user, Post $post): bool
    {
        return true;
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return true;
    }

    /**
     * @param  array<int, int|string>  $ids
     */
    public function attachTags(User $user, Post $post, array $ids): bool
    {
        return true;
    }
}
