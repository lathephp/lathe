---
title: Permission plugin
description: Manage roles and permissions in the panel with lathe/permission, and grant a plugin's own abilities through it.
order: 3
---

# Permission plugin

`lathe/permission` is Lathe's official plugin. It puts [spatie/laravel-permission](https://github.com/spatie/laravel-permission) in the panel: a Roles screen, a Permissions screen, and pickers that give a user their roles and permissions. It lives in its own repository, `lathephp/permission`.

## Install

```bash
composer require lathe/permission:^1.0@beta
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Add Spatie's trait to your User model:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

Lathe finds the plugin through its `composer.json`, so there is no registration step. The plugin needs `spatie/laravel-permission` 7 or 8. If Spatie is missing, the panel stops with a `PluginDependencyException` that tells you to run `composer require spatie/laravel-permission`.

## Turn it on

Installing the plugin does not let anyone manage roles. Until you opt in, the plugin registers no policy, and every request to the Roles and Permissions screens answers `403`. The sidebar group stays hidden. Choose one of two ways to opt in.

**Use the shipped policies.** They let every user who passes the `viewLathe` gate manage roles and permissions. Lathe logs one warning per policy class when the app boots, so you remember the choice:

```php
use Lathe\Lathe;
use Lathe\Permission\PermissionPlugin;

protected function registerPlugins(): void
{
    Lathe::registerPlugins(PermissionPlugin::make()->withDefaultPolicies());
}
```

Put this in `app/Providers/LatheServiceProvider.php`. A plugin you register by hand wins over the one discovery finds.

**Use your own policies.** Name a policy class for each model. Lathe registers them for the role and permission classes that Spatie's registrar reports, so custom Spatie models work:

```php
Lathe::registerPlugins(
    PermissionPlugin::make()
        ->useRolePolicy(App\Policies\RolePolicy::class)
        ->usePermissionPolicy(App\Policies\PermissionPolicy::class),
);
```

A policy has the usual methods: `viewAny`, `view`, `create`, `update`, `delete`, `restore` and `forceDelete`.

```php
namespace App\Policies;

use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super-admin');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    // create, update, delete, restore and forceDelete follow the same pattern.
}
```

## What it adds

The plugin is called **Access Control**. It adds two Resources in a sidebar group of the same name.

- **Roles** at `/lathe/resources/roles`, on Spatie's role model.
- **Permissions** at `/lathe/resources/permissions`, on Spatie's permission model.

Both have a sortable ID, a required `name` of up to 255 characters, and a created-at date. Names must be unique: Lathe checks it on create, and on update it ignores the record you edit. A role has a **Permissions** picker. A permission has a **Roles** picker. Both also list their users on the detail page, through the User Resource of your app, when you have one registered.

The pickers search on the server. They load `limits.picker_results` options per request (25 by default; see [Configuration](../getting-started/configuration.md)) and sort by name. They save after the record is saved, in the same transaction, so they work on a create form too.

When your app configures more than one auth guard, both screens also show a required **Guard Name** select. It lists the guards and defaults to the app's default guard. With one guard the field stays hidden.

### Pickers for your User Resource

Add the pickers to the User Resource so you can assign access from a user's form:

```php
use Lathe\Permission\Fields\PermissionMultiSelectField;
use Lathe\Permission\Fields\RoleMultiSelectField;

public function fields(LatheRequest $request): array
{
    return [
        Field::text('Name')->required(),
        Field::email('Email')->required(),

        new RoleMultiSelectField,
        new PermissionMultiSelectField,
    ];
}
```

| Field | Default label and attribute | Saves with |
|---|---|---|
| `Lathe\Permission\Fields\RoleMultiSelectField` | Roles, `roles` | `syncRoles()` |
| `Lathe\Permission\Fields\PermissionMultiSelectField` | Permissions, `permissions` | `syncPermissions()` |
| `Lathe\Permission\Fields\RoleSelectField` | Role, `role` | `syncRoles()` with one name |

Each takes a label, an attribute and an optional resolver. They show on forms and the detail page and hide on the index. They save only when the request carries the attribute, so a form that leaves them out changes nothing. `RoleSelectField` suits an app where a user holds one role. It sends the roles to the form when there are at most 100.

## Change the Resources

Swap in your own Resource classes with the setters. Each returns the plugin:

```php
PermissionPlugin::make()
    ->useRoleResource(App\Lathe\Resources\Role::class)
    ->usePermissionResource(App\Lathe\Resources\Permission::class)
```

Use this when your app already has a Resource with the uriKey `roles` or `permissions`. Without it, that Resource stops the panel from booting with `ResourceUriKeyCollisionException`.

## Cache

Saving or deleting a role or a permission flushes Spatie's permission cache. A change made in the panel applies on the next request.

## Grant a plugin's own abilities

A plugin decides who sees it with `canSee()` or `canSeeWhen()`. Spatie hooks its permissions into Laravel's Gate, so a permission name works as a Gate ability. That is how Heartbeat, the plugin from [Plugins](plugins.md), limits itself to chosen users.

Register Heartbeat by hand with an ability. The hand-registered instance replaces the one discovery would add:

```php
Lathe::registerPlugins(
    PermissionPlugin::make()->withDefaultPolicies(),
    Heartbeat::make()->canSeeWhen('view heartbeat'),
);
```

Now only a user who holds the `view heartbeat` permission sees Heartbeat. Everyone else gets no sidebar entry, and the page and its API answer `403`. The user must also pass the `viewLathe` gate to enter the panel.

Create the permission and give it out in the panel:

1. Open **Access Control**, **Permissions**, and create `view heartbeat`.
2. Open **Roles**, create `operator`, and pick `view heartbeat` in **Permissions**.
3. Open **Users**, edit a user, and pick `operator` in **Roles**.

Or do the same in code, for example in a seeder:

```php
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Permission::findOrCreate('view heartbeat');
Role::findOrCreate('operator')->givePermissionTo('view heartbeat');
```

The same works for a page or a Resource plugin: call `canSeeWhen('view reports')` on a `Page`, or on a menu item, and the permission decides.

## Limits

- The users of a role stay on the detail page only. Assign roles from a user's edit form.
- Spatie's tables are named `roles` and `permissions`. A table of your own with either name collides with them.
- Roles and permissions do not soft delete. `restore` and `forceDelete` exist only to complete the policy contract.

## What you see

After you opt in, the sidebar shows an Access Control group with Roles and Permissions. Creating a role with a name and two permissions saves it, and its detail page lists both permissions. Editing a user and choosing the role shows the role on that user's detail page. Without the opt-in, the group is missing and `/lathe/resources/roles` shows the 403 page.
