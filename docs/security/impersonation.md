---
title: Impersonation
description: Let trusted users act as another user to reproduce a problem, with clear rules for who may impersonate whom.
order: 3
---

# Impersonation

Impersonation lets one user see the app as another user. Support staff use it to reproduce a problem. Nothing is on by default: nobody may impersonate until you say who.

## Turn it on

Add the `Impersonatable` trait to your user model, and override `canImpersonate()` for the users you trust:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Lathe\Auth\Impersonatable;

class User extends Authenticatable
{
    use Impersonatable;

    public function canImpersonate(): bool
    {
        return $this->is_admin;
    }
}
```

The trait gives `canImpersonate()` a default of `false`, so adding it alone opens nothing. It also gives `canBeImpersonated()` a default of `true`.

The Resource for the user model then shows "Impersonate" in the row menu and in the detail menu. It shows only for a user who may impersonate that row.

## Decide who can be impersonated

Override `canBeImpersonated()` to protect some users. This keeps administrators from being impersonated:

```php
public function canBeImpersonated(): bool
{
    return ! $this->is_admin;
}
```

A user may start impersonating when all of these hold:

- Both the user and the target use the `Impersonatable` trait.
- They are two different people. Impersonating yourself answers 403 with "You cannot impersonate yourself."
- The user's `canImpersonate()` returns `true`.
- The target's `canBeImpersonated()` returns `true`.
- No impersonation is running.

If one fails, the request answers 403 and no menu entry shows.

The panel finds the target through the scoped query of the user Resource. A user that Resource cannot reach, such as one from another tenant, answers 404. See [Tenancy](tenancy.md).

## Start and stop

Pick "Impersonate" and the panel signs you in as the target. A bar at the top of every page says "You are currently impersonating" and the user's name, with a "Stop Impersonating" button. Press it and you are signed in as yourself again, with the same "remember me" choice you had.

The start goes to the path in `lathe.impersonation.started`. A stop goes back to the page where you started, if that page is on the same site. Otherwise it goes to `lathe.impersonation.stopped`. Both default to `/` and can be set in `.env`:

```bash
LATHE_IMPERSONATION_STARTED=/dashboard
LATHE_IMPERSONATION_STOPPED=/lathe
```

Lathe checks the saved page before it uses it. A relative path or a URL on your own host is fine. Anything that points to another host, such as `//evil.test`, is ignored.

If the user who started the session no longer exists, a stop clears the impersonation and does not sign anyone in.

## Events and the audit log

Two events fire, both from `Lathe\Events`: `ImpersonationStarted` and `ImpersonationStopped`. Each carries the `impersonator`, the `impersonated` user and the `redirectTo` path. Listen for them to notify someone or write your own log:

```php
use Illuminate\Support\Facades\Event;
use Lathe\Events\ImpersonationStarted;

Event::listen(ImpersonationStarted::class, function (ImpersonationStarted $event) {
    logger()->warning('Impersonation started', [
        'by' => $event->impersonator->getKey(),
        'as' => $event->impersonated->getKey(),
    ]);
});
```

The panel also records both events in the audit log with the impersonator as the actor. See [Audit log](../taking-action/audit-log.md).

## Check the state in code

`Lathe::impersonator()` returns the object that does the work. It binds the `Lathe\Auth\Contracts\ImpersonatesUsers` contract in the container. Ask it whether the request is impersonating:

```php
use Lathe\Lathe;

if (Lathe::impersonator()->impersonating($request)) {
    // Hide a button, or mark an export.
}
```

The panel also answers `GET /lathe/api/impersonate` with `{"impersonating": true}` or `false`.

## Replace the implementation

Bind your own class to `ImpersonatesUsers` to change how the session is kept. The contract has six methods: `impersonate()`, `stopImpersonating()`, `impersonating()`, `flushImpersonationData()`, `redirectAfterStartingImpersonation()` and `redirectAfterStoppingImpersonation()`.

```php
$this->app->bind(\Lathe\Auth\Contracts\ImpersonatesUsers::class, \App\Auth\MyImpersonation::class);
```

The checks for who may impersonate whom stay in the controller. They do not move with your class.

## What you see

For a user who may impersonate, the Users Resource shows an "Impersonate" entry in the row menu and the detail menu. After you pick it, a bar across the top says who you are acting as and offers "Stop Impersonating". Pressing it returns you to the page you started from.
