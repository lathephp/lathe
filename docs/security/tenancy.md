---
title: Tenancy
description: Keep each tenant's records apart in the panel by scoping every Resource query, with stancl/tenancy or spatie/laravel-multitenancy.
order: 4
---

# Tenancy

Lathe 1.0 has no built-in multi-tenancy. It does not know what a tenant is, and it adds no tenant column, switch or menu. It gives you one thing instead: every read and write in the panel goes through one scoped query for the Resource. You scope that query once, and the whole panel follows. A tenancy package tells you who the current tenant is.

This page shows the pattern with two packages, `stancl/tenancy` and `spatie/laravel-multitenancy`. Any other way to find the current tenant works the same.

## How the scoped query works

For each Resource, Lathe builds one query and starts from the model's query with its global scopes on. Then it runs the hook for the kind of request. The hooks are static methods on the Resource, and each takes the request and the query and returns the query:

| Hook | Narrows |
|---|---|
| `indexQuery()` | The index, counts, summaries and exports. Global search and widgets use it too, unless you override `searchQuery()` or `widgetQuery()`. |
| `detailQuery()` | The detail page, peek, delete, force delete and restore. |
| `editQuery()` | The edit form, saving and inline edit. |
| `relatableQuery()` | The records a relation picker offers. |
| `replicateQuery()` | The records that can be copied. |

Actions, lenses and relation lookups use the same query. A record outside the scope answers 404, as if it did not exist, so a user cannot find another tenant's id by trying numbers.

> Note: `detailQuery()`, `editQuery()`, `relatableQuery()` and `replicateQuery()` do not fall back to `indexQuery()`. Scope each of them, or a form or a picker reads rows your index hides.

Dashboard widgets that name no Resource are not scoped. Scope their own queries yourself.

## Find the current tenant first

The panel is a normal Laravel app, so your tenancy package must have chosen the tenant before the panel code runs. Add its middleware to the `middleware` list in `config/lathe.php`, ahead of the Lathe classes, or serve the panel on a tenant domain with the package's routes. Check this first. A request with no current tenant must not see rows from all tenants.

## One scope for a whole panel

Put the hooks in a trait so each Resource adds one line:

```php
<?php

namespace App\Lathe\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Http\Requests\LatheRequest;

trait ScopedToTenant
{
    public static function indexQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::forCurrentTenant($query);
    }

    public static function detailQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::forCurrentTenant($query);
    }

    public static function editQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::forCurrentTenant($query);
    }

    public static function relatableQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::forCurrentTenant($query);
    }

    public static function replicateQuery(LatheRequest $request, Builder $query): Builder
    {
        return static::forCurrentTenant($query);
    }
}
```

The `forCurrentTenant()` method is yours to write. It takes the query and returns it narrowed to the current tenant. The next two sections write it for each package.

```php
use App\Lathe\Concerns\ScopedToTenant;
use Lathe\Resources\Resource;

class Order extends Resource
{
    use ScopedToTenant;
}
```

## stancl/tenancy

With the multi-database setup, `stancl/tenancy` switches the database connection for the tenant. Every query already reads the tenant's own database, so you need no scope in Lathe. Make sure the tenancy middleware runs before the panel.

With a single database, give each model the package's `BelongsToTenant` trait. It adds a global scope on the tenant column. Lathe starts every query with global scopes on, so that scope already applies to the panel. You write no hooks.

For a model without the trait, scope it by hand. Put the method in a trait the Resource uses, or in the Resource:

```php
use Illuminate\Database\Eloquent\Builder;

protected static function forCurrentTenant(Builder $query): Builder
{
    return $query->where($query->qualifyColumn('tenant_id'), tenant('id'));
}
```

`tenant('id')` is the package's helper for the current tenant.

## spatie/laravel-multitenancy

`spatie/laravel-multitenancy` keeps the current tenant on its `Tenant` model. Ask it for `Tenant::current()`. It returns `null` when no tenant is set. Then the query must return nothing:

```php
use Illuminate\Database\Eloquent\Builder;
use Spatie\Multitenancy\Models\Tenant;

protected static function forCurrentTenant(Builder $query): Builder
{
    $tenant = Tenant::current();

    return $tenant === null
        ? $query->whereRaw('0 = 1')
        : $query->where($query->qualifyColumn('tenant_id'), $tenant->getKey());
}
```

If you use the package's one database per tenant, its tenant tasks switch the connection and you need no scope in Lathe. The scope above is for the single-database setup.

## Writes and checks

Scoping narrows what a user can read, edit and delete. It does not fill the tenant column of a new record. Do that on the model, in a `creating` observer or in the package's trait, so that a record made in the panel belongs to the current tenant.

Test it. The `assertScoped()` helper fails when a query on a model's table runs without the column its global scopes filter on. See [Testing](../your-first-resource/testing.md). Also check that a user of tenant A gets 404 for an id from tenant B on the detail page, the edit form and a relation picker. Related pages that lean on the scope: [Relationships](../your-first-resource/relationships.md) and [Impersonation](impersonation.md), whose target is found through the user Resource's scope.

## What you see

A user of one tenant sees only that tenant's rows in the index, search, widgets, exports and relation pickers. Opening a record that belongs to another tenant shows a 404 page, not a 403.
