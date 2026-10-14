<?php

namespace Lathe\ActionEvents;

/**
 * The policy the audit log ships with: it denies everything. The host registers its own
 * policy for the action event model that grants viewAny and view to whoever may read the log.
 */
class ActionEventPolicy
{
    public function viewAny(object $user): bool
    {
        return false;
    }

    public function view(object $user, ActionEvent $event): bool
    {
        return false;
    }

    public function create(object $user): bool
    {
        return false;
    }

    public function update(object $user, ActionEvent $event): bool
    {
        return false;
    }

    public function delete(object $user, ActionEvent $event): bool
    {
        return false;
    }

    public function restore(object $user, ActionEvent $event): bool
    {
        return false;
    }

    public function forceDelete(object $user, ActionEvent $event): bool
    {
        return false;
    }

    public function replicate(object $user, ActionEvent $event): bool
    {
        return false;
    }
}
