---
title: Events
description: Listen to Lathe's Laravel events for records, relations, actions and requests, and run code once per boot or on every panel request.
order: 7
---

# Events

Lathe fires ordinary Laravel events when a record is created, changed or deleted, when a relation changes, when an action runs and when a panel request starts. You listen to them the way you listen to any event. Next to the events, the `Lathe` class has a few callbacks that run code once when the app boots or on every panel request.

> Note: The events are plain classes with public properties. You listen with `Event::listen()` or a listener class. The Resource events fire for changes made through the panel, not for changes your own code makes to a model.

## Resource events

Lathe fires an event after each write to a record. Each carries the Eloquent model and the Resource class that owns it, as `$model` and `$resource`:

| Event | Fires when | Properties |
|---|---|---|
| `Lathe\Events\ResourceCreated` | A record is created from the create form | `Model $model`, `string $resource` |
| `Lathe\Events\ResourceUpdated` | A record is updated, from the edit form or an inline edit | `Model $model`, `string $resource`, `array $original`, `array $changes` |
| `Lathe\Events\ResourceDeleted` | A record is deleted | `Model $model`, `string $resource` |
| `Lathe\Events\ResourceForceDeleted` | A record is deleted for good | `Model $model`, `string $resource` |
| `Lathe\Events\ResourceRestored` | A soft-deleted record is restored | `Model $model`, `string $resource` |

`$resource` is the Resource class, such as `App\Lathe\Resources\Post`, not the model class. On `ResourceUpdated`, `$original` holds the changed attributes as they were and `$changes` as they are now. A delete, force delete or restore fires its event for each record, also when many records are chosen at once.

These events fire inside the database transaction of the write. A listener that throws rolls the write back. A listener that calls out to another system should wait for the commit. Make it a queued listener with `public bool $afterCommit = true;`.

Events are separate from a Resource's own hooks such as `afterCreate()`. Use an event for a side effect that spans Resources, such as clearing a cache, sending a webhook or updating a search index. Use a Resource hook when the logic belongs to that one Resource.

## Relation events

Attaching and detaching records on a many-to-many relation fire these events, also inside the transaction:

| Event | Fires when | Properties |
|---|---|---|
| `Lathe\Events\ResourceAttached` | Records are attached to a parent | `Model $parent`, `string $resource`, `string $relationship`, `array $ids`, `array $pivot` |
| `Lathe\Events\ResourceDetached` | Records are detached | `Model $parent`, `string $resource`, `string $relationship`, `array $ids`, `array $rows` |
| `Lathe\Events\ResourceAttachmentUpdated` | The pivot values of an attached record change | `Model $parent`, `string $resource`, `string $relationship`, `array $ids`, `array $original`, `array $changes` |

Here `$resource` is the parent's Resource class and `$ids` are the related keys.

## Action events

| Event | Fires when | Properties |
|---|---|---|
| `Lathe\Events\ActionExecuted` | An action has run on its models | `object\|string $action`, `Collection $models` |

An action that runs at once fires the event once. A queued action fires it once for each chunk. `$models` is the `Illuminate\Support\Collection` of models the action ran on. For a history you can query, use the [audit log](../taking-action/audit-log.md) and not this event.

## The serving event

| Event | Fires when | Properties |
|---|---|---|
| `Lathe\Events\ServingLathe` | A panel request starts | `Request $request` |

Lathe fires it once at the start of every panel request, before the response is built. It never fires for your own routes. The `Lathe::serving()` callback below listens for it.

## Impersonation events

`Lathe\Events\ImpersonationStarted` and `Lathe\Events\ImpersonationStopped` fire when an administrator starts or stops acting as another user. See [Impersonation](../security/impersonation.md).

## Listen to events

A listener class with a `handle()` method receives the event:

```php
namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Lathe\Events\ResourceCreated;

class SyncSearchIndex implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(ResourceCreated $event): void
    {
        Http::post('https://search.example.com/index', [
            'type' => class_basename($event->model),
            'id' => $event->model->getKey(),
            'resource' => $event->resource,
        ]);
    }
}
```

Laravel finds listeners in `app/Listeners` by the type of the `handle()` argument. Or register one by hand in a service provider:

```php
use App\Listeners\SyncSearchIndex;
use Illuminate\Support\Facades\Event;
use Lathe\Events\ResourceCreated;

public function boot(): void
{
    Event::listen(ResourceCreated::class, SyncSearchIndex::class);
}
```

For a short piece of logic, use a closure:

```php
Event::listen(ResourceDeleted::class, function (ResourceDeleted $event) {
    logger("Deleted {$event->resource} #{$event->model->getKey()}");
});
```

## Lifecycle callbacks

The `Lathe` class has callbacks for code that must run at a fixed point. Register them in `boot()` of `app/Providers/LatheServiceProvider.php`, after `parent::boot()`.

### Lathe::serving

`Lathe::serving(Closure $callback)` runs the callback at the start of every panel request. It receives the `ServingLathe` event. Use it for state that depends on the request, such as values for the browser or a locale:

```php
use Lathe\Events\ServingLathe;
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::serving(function (ServingLathe $event) {
        Lathe::provideToScript([
            'timezone' => $event->request->user()?->timezone ?? config('app.timezone'),
        ]);
    });
}
```

A plugin has its own `serving(Request $request)` hook for this. See [Plugins](plugins.md).

### Lathe::booted

`Lathe::booted(Closure $callback)` runs the callback once, when the app has booted, or at once if it already has. The callback takes no arguments. Use it for one-time setup that does not depend on a request:

```php
use Illuminate\Support\Facades\Gate;
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::booted(function () {
        Gate::define('view-metrics', fn ($user) => $user->isAdmin());
    });
}
```

> Note: Put anything that runs once in `booted()`. A callback in `serving()` runs on every request, which costs time. Under Octane, `booted()` runs once for each worker.

The setters that define the panel, such as `Lathe::auth()`, `Lathe::userLocale()` and `Lathe::footer()`, must run in a service provider's `boot()`. Called while a request is served, they throw a `LogicException`, because the change would leak into the next request of a long-lived worker.

### Lathe::isServing and Lathe::whenServing

These let code outside the panel, such as a model observer, react to whether a panel request is running.

`Lathe::isServing(): bool` tells whether a panel request is being served:

```php
if (Lathe::isServing()) {
    // A panel request is running.
}
```

`Lathe::whenServing(Closure $callback, ?Closure $default = null): mixed` runs `$callback` during a panel request and `$default` otherwise. The first receives the `LatheRequest`, the second the current `Request`. If you give no `$default` outside a panel request, the result is `null`.

```php
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lathe;

class PostObserver
{
    public function saving(Post $post): void
    {
        $post->last_touched_by = Lathe::whenServing(
            fn (LatheRequest $request) => $request->user()?->id,
        );
    }
}
```

This records the admin only when a post is saved through the panel.

### Lathe::ensureBooted

`Lathe::ensureBooted()` runs the serving callbacks and every plugin's `serving()` once, outside a web request. Use it in an Artisan command or a script that needs the panel set up, for example to read the assets a plugin registers while serving. It binds no request, so the first real request is still served as usual.

## What you see

Nothing changes on screen. After you listen to `ResourceCreated`, creating a record in the panel runs your listener, and the new record is saved only if the listener does not throw. A `Lathe::serving()` callback runs on every panel page and API request, and a `Lathe::booted()` callback runs once when the app starts.
