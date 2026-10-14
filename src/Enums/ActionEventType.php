<?php

namespace Lathe\Enums;

use Lathe\Lathe;

/**
 * What an action event row records.
 */
enum ActionEventType: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case ForceDelete = 'force-delete';
    case Restore = 'restore';
    case Attach = 'attach';
    case Detach = 'detach';
    case UpdateAttached = 'update-attached';
    case ActionRan = 'action-ran';
    case ImpersonationStarted = 'impersonation-started';
    case ImpersonationStopped = 'impersonation-stopped';

    public function label(): string
    {
        return Lathe::__(match ($this) {
            self::Create => 'Create',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::ForceDelete => 'Force Delete',
            self::Restore => 'Restore',
            self::Attach => 'Attach',
            self::Detach => 'Detach',
            self::UpdateAttached => 'Update Attached',
            self::ActionRan => 'Action Ran',
            self::ImpersonationStarted => 'Impersonation Started',
            self::ImpersonationStopped => 'Impersonation Stopped',
        });
    }
}
