# lathe/permission

Roles and permissions from [spatie/laravel-permission](https://github.com/spatie/laravel-permission), managed in the Lathe panel.

## Install

```bash
composer require lathe/permission:^1.0@beta
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

Add `Spatie\Permission\Traits\HasRoles` to your User model. Lathe discovers the plugin from the package's `composer.json`, so there is no registration step.

## Opt in

Nobody may manage roles or permissions until the app opts in. Register the plugin with the shipped policies, which let every user who passes `viewLathe` manage both and log one boot warning per policy class:

```php
Lathe::registerPlugins(PermissionPlugin::make()->withDefaultPolicies());
```

Or register your own policies:

```php
Lathe::registerPlugins(
    PermissionPlugin::make()
        ->useRolePolicy(RolePolicy::class)
        ->usePermissionPolicy(PermissionPolicy::class),
);
```

A plugin you register by hand wins over the discovered one. `useRoleResource()` and `usePermissionResource()` swap in your own Resources.

## Cache

Saving or deleting a role or a permission flushes Spatie's permission cache, so a change made in the panel applies on the next request.

## Known limits

- A host table named `roles` or `permissions` collides with Spatie's tables.
- A host Resource with the uriKey `roles` or `permissions` stops the panel from booting with `ResourceUriKeyCollisionException`. Point the plugin at it with `useRoleResource()` or `usePermissionResource()`.

## Development

This repository is a read-only copy of `plugins/permission` in [lathephp/lathe](https://github.com/lathephp/lathe), where the plugin is developed and released together with Lathe. Send issues and pull requests there.

To run the plugin's own checks here:

```bash
composer update
composer test
composer analyse
composer lint
composer types
```

CI runs the tests on PHP 8.4 and 8.5, each on the highest versions and after `composer update --prefer-lowest` (spatie/laravel-permission 7.x).
