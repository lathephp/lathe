# lathe/permission

Roles and permissions from [spatie/laravel-permission](https://github.com/spatie/laravel-permission), managed in the Lathe panel.

## Install

```bash
composer require lathe/permission
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

Until this package moves to its own repository, `composer.json` installs `lathe/lathe` from the checkout two folders up through a path repository.

```bash
composer install
composer test
composer analyse
composer lint
composer types
```

`scripts/gates.sh` runs every gate on PHP 8.5 and in the `lathe-php84` container (the image `scripts/matrix.sh` builds at the repo root), each on the highest versions and after `composer update --prefer-lowest` (Spatie 7.x). It prints the resolved Spatie version and one `rc=` line per lane and gate, restores the highest install, and exits 1 when any rc is not 0.

```bash
bash scripts/gates.sh
```
