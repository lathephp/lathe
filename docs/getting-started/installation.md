---
title: Installation
description: Install Lathe into a Laravel 13 app, create a user and open the panel.
order: 1
---

# Installation

Lathe is an admin panel builder for Laravel. It installs from Packagist like any Laravel package and needs no licence key.

## Requirements

- PHP 8.4 or newer
- Laravel 13
- MySQL 8.4+, MariaDB 10.11+, PostgreSQL 15+ or SQLite

The panel's JavaScript and CSS are already built inside the package. Your app does not need Node to run Lathe, only to build [custom components](../extending/frontend.md).

## Install with Composer

```bash
composer require lathe/lathe:^1.0@beta
php artisan lathe:install
```

Lathe is in beta, so the version constraint names it: without `@beta`, Composer refuses a beta in an app whose minimum stability is `stable`. Once 1.0.0 is out, `composer require lathe/lathe` is enough.

`lathe:install` does six things and leaves any file that already exists alone:

1. Publishes `config/lathe.php` (see [Configuration](configuration.md)).
2. Publishes Lathe's migrations.
3. Writes `app/Providers/LatheServiceProvider.php` and adds it to `bootstrap/providers.php`.
4. Writes a User Resource in `app/Lathe/Resources/User.php` and its policy in `app/Policies/UserPolicy.php`.
5. Writes the Main dashboard in `app/Lathe/Dashboards/Main.php`.
6. Runs the migrations.

`lathe:install` publishes through two tags you can also run on their own: `lathe-config` and `lathe-migrations`. Two more tags copy a file into your app when you want it alone. `lathe-translations` writes `lang/vendor/lathe/en.json`, and `lathe-resource` writes the User Resource to `app/Lathe/Resources/User.php`, in the `App` namespace and with the model of your `auth.providers.users` provider already filled in. Change the namespace by hand if your app uses another.

## Create a user

```bash
php artisan lathe:user
```

It asks for a name, an email and a password. Pass `--name`, `--email` and `--password` to skip the prompts in a script.

## Allow the user in

Nobody can open the panel until you say who may. Open `app/Providers/LatheServiceProvider.php` and add the email to the `viewLathe` gate:

```php
protected function gate(): void
{
    Gate::define('viewLathe', fn (object $user): bool => in_array(data_get($user, 'email'), [
        'taylor@example.com',
    ]));
}
```

The gate is checked in every environment, local included. For a rule that is not a list of emails, such as a role column, return your own check from the closure. To replace the whole check, override `authorization()` in the same provider and call `Lathe::auth()` with a callback.

## Open the panel

Serve the app and open `/lathe`. Sign in with the user you created. The panel lists the Users Resource in the sidebar and shows the Main dashboard.

## Check the setup

```bash
php artisan lathe:doctor
```

It prints one line per check and exits 1 when a check fails. Run it after an upgrade and in your deploy script.

## Update Lathe

```bash
composer update lathe/lathe
php artisan migrate
```

The built assets come with the package and are served with a content hash in their address, so a browser never keeps a stale copy. If a plugin registers scripts or styles, run `php artisan lathe:assets` after updating. Read the [upgrade guide](upgrade.md) before moving to a new minor or major version.

## Uninstall

```bash
php artisan lathe:uninstall
composer remove lathe/lathe
```

`lathe:uninstall` removes the service provider, the published config and the copied assets. Your Resources and Lathe's tables stay until you delete them.

## What you see

After `lathe:install`, `lathe:user` and adding the email to the gate, `/lathe` shows the sign-in screen. After signing in you see the sidebar with Users and the Main dashboard.
