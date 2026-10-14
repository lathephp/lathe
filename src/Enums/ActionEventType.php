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
        return match ($this) {
            self::Create => Lathe::__('Create'),
            self::Update => Lathe::__('Update'),
            self::Delete => Lathe::__('Delete'),
            self::ForceDelete => Lathe::__('Force Delete'),
            self::Restore => Lathe::__('Restore'),
            self::Attach => Lathe::__('Attach'),
            self::Detach => Lathe::__('Detach'),
            self::UpdateAttached => Lathe::__('Update Attached'),
            self::ActionRan => Lathe::__('Action Ran'),
            self::ImpersonationStarted => Lathe::__('Impersonation Started'),
            self::ImpersonationStopped => Lathe::__('Impersonation Stopped'),
        };
    }
}
