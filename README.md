# Lathe

[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Lathe is an admin panel builder for Laravel on Inertia, React and shadcn/ui. You describe your models as Resources in PHP, and Lathe gives you the lists, forms, detail pages, actions, dashboards and accounts, fast on tables with millions of rows.

- Docs: https://lathephp.com/docs
- Benchmark against Filament: https://github.com/lathephp/benchmarks

## Requirements

- PHP 8.4 or newer
- Laravel 13
- MySQL 8.4+, MariaDB 10.11+, PostgreSQL 15+ or SQLite

## Install

```bash
composer require lathe/lathe:^1.0@beta
php artisan lathe:install
php artisan lathe:user
```

Lathe is in beta, so the version constraint names it; once 1.0.0 is out, `composer require lathe/lathe` is enough.

Then add the new user's email to `gate()` in `app/Providers/LatheServiceProvider.php`:

```php
protected function gate(): void
{
    Gate::define('viewLathe', fn (object $user): bool => in_array(data_get($user, 'email'), [
        'you@example.com',
    ]));
}
```

Open `/lathe` in your browser and sign in. The [installation guide](docs/getting-started/installation.md) explains each step.

## Security

Please report vulnerabilities privately, as described in [SECURITY.md](SECURITY.md).

## Contributing

Clone the repository, then run `composer install` and `pnpm install`. `composer serve` builds the workbench app and serves the panel at http://127.0.0.1:8000/lathe (sign in as admin@lathephp.com with the password `password`). `composer qa` runs the lint, static analysis, type coverage and test gates that every change must pass.

## License

Lathe is open-source software under the [MIT license](LICENSE).
