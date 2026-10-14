---
title: Audit log
description: Read, query, switch off and prune the record Lathe keeps of every write and action run.
order: 3
---

# Audit log

Every write made through the panel leaves a row in the `lathe_action_events` table: who did what to which record, when, from where, and for actions whether each row finished. The same table is the audit log.

Lathe writes the row in the same database transaction as the change. If the change rolls back, the row is never saved.

## Open the log

The shipped policy for the log denies everyone, so the log stays hidden until you grant access. Register a policy for the action event model in a service provider, and grant `viewAny` and `view`:

```php
use Illuminate\Support\Facades\Gate;
use Lathe\ActionEvents\ActionEvent;

Gate::policy(ActionEvent::class, App\Policies\ActionEventPolicy::class);
```

```php
namespace App\Policies;

use App\Models\User;
use Lathe\ActionEvents\ActionEvent;

class ActionEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ActionEvent $event): bool
    {
        return $user->isAdmin();
    }
}
```

The log is read only. Whatever your policy says about writing, nobody can create, change or delete a row through the panel. See [Policies](../security/policies.md).

Lathe lists the log as a Resource at `/lathe/resources/action-events`. It has no entry in the sidebar. Open it by address, or from a record: add the `Lathe\ActionEvents\Actionable` trait to a model, and its detail page gets a folded **Action Events** panel with the events of that record. The panel shows only to a user who may view the log.

```php
use Lathe\ActionEvents\Actionable;

class Order extends Model
{
    use Actionable;
}
```

The trait adds an `actions()` relation, which you can query yourself:

```php
$order->actions()->latest()->get();
```

## What Lathe records

| Type | When |
|---|---|
| `create` | A record was created. |
| `update` | A record was updated. Lathe stores the original values and the changed ones. |
| `delete` | A record was deleted. |
| `force-delete` | A record was deleted for good. |
| `restore` | A soft-deleted record was restored. |
| `attach` | A related record was attached to a `belongsToMany` or `morphToMany` relation. |
| `detach` | A related record was detached. |
| `update-attached` | A pivot row was updated. |
| `action-ran` | An action ran. |
| `impersonation-started` | A user started to impersonate another. |
| `impersonation-stopped` | The impersonation ended. |

The `Lathe\Enums\ActionEventType` enum holds these types.

Each row carries:

- the user who acted. A row from a console command or a job without a user shows "System",
- the record that changed (`actionable`) and, for relations, the related record (`target`),
- the `original` values and the `changes`,
- for an action, its URI key and the values of its form (`fields`),
- the `status`: `waiting`, `running`, `finished` or `failed`, and the `exception` message when it failed,
- a `batch_id` that groups the rows of one request or one action run,
- the Resource class, the IP address and the user agent.

What Lathe leaves out:

- Attributes in the model's `$hidden` list.
- Values of password and token fields. Lathe stores `[redacted]`.
- Uploaded files. Lathe stores `{name, size, mime}`.
- Text past 1,000 characters. Lathe cuts it and adds `...(truncated)`.

For `update`, `changes` holds only the attributes whose value differs.

```php
$event->original; // ['name' => 'John', 'email' => 'john@example.com']
$event->changes;  // ['name' => 'Jane']
```

Impersonation events record the impersonator as the user and the impersonated user as the record. See [Impersonation](../security/impersonation.md).

## Status of an action run

An action run writes one row per model. Inline runs start at `running` and end at `finished` or `failed` for each row. A queued run starts at `waiting`, moves to `running` when a worker takes the chunk, and ends the same way. If a chunk throws, its rows become `failed` with the exception message. A standalone action writes one row with no record.

Rows that already finished or failed never change again.

## Query the log

`Lathe\ActionEvents\ActionEvent` is an Eloquent model with these scopes:

```php
use Lathe\ActionEvents\ActionEvent;
use Lathe\Enums\ActionEventType;

ActionEvent::forActionable($order)->latest()->get();     // every event of one record
ActionEvent::ofType(ActionEventType::Update)->get();     // one type
ActionEvent::byUser($admin)->get();                      // a user, a model or an id
ActionEvent::forBatch($batchId)->get();                  // one request or run
```

It also has `actionable()`, `target()` and `user()` relations.

## Switch recording off

Recording is on by default. There are four switches, from wide to narrow.

For the whole panel, in `config/lathe.php`:

```php
'action_events' => [
    'enabled' => false,
],
```

For one Resource, return `false` from its static `recordsActionEvents()` method:

```php
public static function recordsActionEvents(): bool
{
    return false;
}
```

For one action, call `withoutActionEvents()` or use `#[WithoutActionEvents]`:

```php
(new RefreshCache)->withoutActionEvents()
```

For a block of code, such as a seeder or an import you run yourself:

```php
use Lathe\Lathe;

Lathe::withoutActionEvents(function () {
    Order::query()->update(['synced' => true]);
});
```

Without a closure, `Lathe::withoutActionEvents()` turns recording off for the rest of the request or job. The recorder can also be switched by hand with `disable()` and `enable()` on `Lathe\ActionEvents\ActionEventRecorder`, which `Lathe::actionEvents()` returns.

## Use your own model

To add scopes or change the table, extend `ActionEvent` and tell Lathe:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::usingActionEvent(\App\Models\AuditEvent::class);
}
```

## Prune old events

The table grows. Lathe prunes it for you: `lathe:prune` runs daily from Lathe's own schedule, and it calls the action event pruner. Two keys in `config/lathe.php` set the rules:

```php
'action_events' => [
    'enabled' => true,
    'prune_after_days' => 30,
    'prune_per_actionable' => null,
],
```

`prune_after_days` deletes events older than that many days. `prune_per_actionable` keeps only the newest N events of each record, whatever their age. `null` turns that rule off.

You can run the pruner yourself:

```bash
php artisan lathe:prune-action-events --days=90 --keep=50
```

`--days` and `--keep` override the two keys for one run. `ActionEvent` also implements Laravel's `Prunable` for the age rule, so `model:prune` works too. It does not apply the per-record limit.

See [Commands](../getting-started/commands.md) and [Configuration](../getting-started/configuration.md).

## The table

| Column | Holds |
|---|---|
| `batch_id` | A UUID shared by the rows of one request or run. |
| `user_id` | The acting user's key, as a string. |
| `type` | The event type. |
| `actionable_type`, `actionable_id` | The record that changed. |
| `target_type`, `target_id` | The related record, for attach and detach. |
| `action_name` | The action's URI key. |
| `resource_class` | The Resource that handled the write. |
| `original`, `changes`, `fields` | JSON. |
| `status`, `exception` | The run's state and error message. |
| `ip_address`, `user_agent` | Where the request came from. |

Keys are strings, so the log works with integer, UUID and ULID keys.

## What you see

After you grant access, `/lathe/resources/action-events` lists the log with the action name, who started it, the record it touched, its status and the original and changed values. The list refreshes every 5 seconds, so a queued run moves from waiting to finished while you watch. A record whose model uses `Actionable` shows its own events in a folded panel on its detail page.
