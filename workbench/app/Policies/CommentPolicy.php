<?php

namespace Workbench\App\Policies;

use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Comment $comment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Comment $comment): bool
    {
        return true;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return true;
    }

    public function addCommentable(User $user, Comment $comment): bool
    {
        return true;
    }
}
