---
title: Plugins
description: Add whole screens, routes, settings and detail-page blocks to the panel with plugins and resource plugins.
order: 1
---

# Plugins

A plugin adds a feature to the panel: a page of its own, API routes, sidebar entries, settings, Resources, dashboards and JavaScript. There are two kinds:

- A **plugin** is a full-page feature, such as a status console, a price tracker or a support inbox.
- A **resource plugin** is a block on a Resource's detail page, placed in `fields()` next to the fields. A Stripe inspector or an activity timeline are examples.

A plugin ships in one of two ways:

- **In the app.** A `Plugin` class in `app/Lathe/Plugins` and a React component, built with your app's own extension build. Use this when the feature belongs to one application.
- **As a package.** A Composer package that carries its own routes, menu entry and a compiled JavaScript module. Use this when you want to reuse or publish the feature. See [Plugin distribution](plugin-distribution.md).

> Note: [Pages](pages.md) and plugins overlap. A page is the lightest option: one class and one component for one screen. A plugin can also add Resources, dashboards, widgets, middleware, settings and API routes. Start with a page and move to a plugin when you need more.

This page builds one plugin, Heartbeat. It shows the app name, the PHP and Laravel versions and whether the database answers. The same plugin lives in Lathe's repository at `workbench/lathe-components/Heartbeat`.

## Create a plugin package

Give `lathe:plugin` a `vendor/package` name:

```bash
php artisan lathe:plugin acme/heartbeat
```

The command writes the package to `lathe-components/Heartbeat/`:

```text
lathe-components/Heartbeat/
├── composer.json            # requires lathe/lathe, names the plugin in extra.lathe.plugin
├── package.json             # dev dependencies on @lathephp/core, @lathephp/ui, @lathephp/vite-plugin
├── vite.config.ts           # builds the page with lathe({ mode: 'plugin' })
├── config/heartbeat.php     # defaults, merged into config('heartbeat')
├── routes/api.php           # package routes
├── resources/js/plugin.tsx  # the page, registered with plugin()
├── src/Heartbeat.php        # the Plugin class
├── src/HeartbeatSearch.php  # puts the page in the command palette
└── tests/                   # a Pest test on Lathe's test kit
```

It also adds a `lathe-components/*` path repository and `acme/heartbeat` to the `composer.json` of your app, and a `lathe-components/*` entry to `pnpm-workspace.yaml`. After a yes, it runs `composer update acme/heartbeat`. Pass `--skip-composer` to skip that question, for example in CI. The package name must be lowercase letters, digits and hyphens, and the package part starts with a letter.

Build the page once so the module exists:

```bash
cd lathe-components/Heartbeat
pnpm install && pnpm build
```

The build writes `dist/heartbeat.js`. [Plugin distribution](plugin-distribution.md) explains the build and how to ship it.

### Create an in-app plugin

To keep the plugin inside your app, give a class name and `--inline`:

```bash
php artisan lathe:plugin Insights --inline
```

This writes `app/Lathe/Plugins/Insights.php` and `resources/js/lathe/plugins/Insights.tsx`, and adds an import for the component to `resources/js/lathe/boot.ts`. The first React file also sets up the extension build; see [Frontend](frontend.md). An in-app plugin is not discovered. Register it in `app/Providers/LatheServiceProvider.php`:

```php
use App\Lathe\Plugins\Insights;
use Lathe\Lathe;

protected function registerPlugins(): void
{
    Lathe::registerPlugins(new Insights);
}
```

## Register a plugin

A packaged plugin names its class under `extra.lathe.plugin` in its `composer.json`:

```json
"extra": {
    "lathe": {
        "plugin": "Acme\\Heartbeat\\Heartbeat"
    }
}
```

Lathe reads `vendor/composer/installed.json`, so `composer require` is the whole install. You call nothing.

Registration is by class. If you register a configured instance by hand, discovery skips the default one:

```php
protected function registerPlugins(): void
{
    Lathe::registerPlugins(
        Heartbeat::make()->canSee(fn ($request) => $request->user()->isAdmin()),
    );
}
```

Two plugins with the same `uriKey()` stop the boot with an exception that names both classes. Run `php artisan lathe:plugins` to list what is registered.

## The plugin class

A plugin extends `Lathe\Plugins\Plugin`. Heartbeat needs a menu entry, a script and one API endpoint:

```php
<?php

namespace Acme\Heartbeat;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Lathe\Enums\Icon;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;
use Lathe\Plugins\Plugin;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Heartbeat extends Plugin
{
    public function menu(Request $request): ?array
    {
        return [MenuSection::section('Heartbeat', [MenuItem::plugin(static::class)->icon(Icon::Activity)])];
    }

    public function assets(): array
    {
        return ['scripts' => ['heartbeat' => __DIR__.'/../dist/heartbeat.js']];
    }

    public function routes(Router $router): void
    {
        $router->get('ping', fn () => new JsonResponse(['pong' => true]));
    }

    public function handleRequest(Request $request, string $path): Response
    {
        if (! $request->isMethod('GET') || $path !== 'status') {
            return parent::handleRequest($request, $path);
        }

        return new JsonResponse([
            'app' => config('app.name'),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => $this->database(),
            'time' => now()->toISOString(),
        ]);
    }

    protected function database(): string
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return 'down';
        }

        return 'connected';
    }
}
```

The generated class also has a `register()` that merges `config/heartbeat.php` and a `boot()` that registers the command palette entry. They are left out here.

The plugin's name comes from the class: `name()` is the headline of the class name without a trailing `Plugin`, and `uriKey()` is its kebab form. For `Heartbeat` both give `Heartbeat` and `heartbeat`. Override `name()` to change the label. Override the static `uriKey()` to change the URL segment.

## The lifecycle

Lathe calls three hooks:

| Hook | When | Use it for |
|---|---|---|
| `register(): void` | Once per process, when the plugin is registered | Container bindings, policies, listeners |
| `boot(): void` | Once per process, right after `register()` | Registering dashboards, search providers and translations |
| `serving(Request $request): void` | On every panel request by a user who may see the plugin | State that depends on the request, such as `Lathe::provideToScript()` |

By default `register()` registers your `policies()` and `boot()` registers your `resources()`. Call `parent::register()` and `parent::boot()` when you override them. Neither hook may read the request, because under Octane they run once per worker.

```php
use Lathe\Lathe;

public function serving(Request $request): void
{
    Lathe::provideToScript(['heartbeatInterval' => $this->setting('interval', 30)]);
}
```

The page reads the value with `config('heartbeatInterval')` from [`@lathephp/core`](extension-api.md).

## Add the plugin to the sidebar

`menu()` returns `null`, `[]` or a list of `MenuSection` objects:

- `null` (the default) builds a "Settings" link under a section named for the plugin, when the plugin has `settings()`. Otherwise it adds nothing. Resources do not appear here, because they already show up through their own `group()`.
- `[]` makes a headless plugin with no entry.
- A list is used as it is.

`MenuItem::plugin(string $plugin, ?string $subPath = null)` links to `/lathe/plugins/{uriKey}`, or to a sub-path of it. The sub-path reaches your component as its `path` prop:

```php
MenuSection::section('Heartbeat', [
    MenuItem::plugin(static::class)->icon(Icon::Activity),
    MenuItem::plugin(static::class, 'history')->icon(Icon::Clock),
]);
```

A target appears once in the sidebar. If your menu and Lathe's automatic entry point at the same place, yours wins. See [Menus and navigation](../organizing/menus.md).

## Control who sees a plugin

Call `canSee()` with a closure, or `canSeeWhen()` with a Gate ability:

```php
Lathe::registerPlugins(
    Heartbeat::make()->canSeeWhen('view heartbeat'),
);
```

To use your own logic, override `authorize(Request $request): bool`. A plugin the user may not see is left out of the sidebar. Its page and its API answer `403`. An unknown plugin answers `404`.

The [permission plugin](permission-plugin.md) shows how to grant such an ability through roles.

## Add Resources, dashboards and widgets

A plugin adds more than a page. These contributions register in the once-per-process hooks.

**Resources.** Return Resource classes from `resources()`. `boot()` registers them:

```php
public function resources(): array
{
    return [\Acme\Heartbeat\Resources\Check::class];
}
```

**Dashboards.** Register them in `boot()`:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::registerDashboards(HeartbeatDashboard::class);
}
```

**Widgets.** Return widgets from `widgets()`. A plugin never mounts them. A dashboard of the host opts in by spreading them into its own list:

```php
public function widgets(LatheRequest $request): array
{
    return [
        ...Heartbeat::make()->widgets(),
    ];
}
```

See [Widgets](../organizing/widgets.md) and [Dashboards](../organizing/dashboards.md).

## Declare dependencies

If the plugin needs another package, name a class or interface that package provides in `requires()`. Lathe checks each one when the plugin registers. A missing class throws `Lathe\Exceptions\PluginDependencyException` with a message that names the plugin and the missing classes:

```php
use Carbon\CarbonInterface;

public function requires(): array
{
    return [CarbonInterface::class];
}
```

`php artisan lathe:plugins --diagnose` runs the same check again, with the version ranges and the module manifest.

## Add middleware

Return middleware class names from `middleware()`. Lathe appends them to the `lathe` middleware group, so they run on every panel request while the plugin is registered:

```php
public function middleware(): array
{
    return [\App\Http\Middleware\TrackPluginUsage::class];
}
```

## Add a settings page

Return a schema from `settings()` to give the plugin a form at `/lathe/plugins/{uriKey}/settings`. The schema is the same list you return from a Resource's `fields()`. It takes fields, `Lathe\Layout\Section`, `Lathe\Layout\Tab::group()` and `Lathe\Layout\Grid`. Return `null`, the default, for no page, no route and no storage.

```php
use Lathe\Field\Field;
use Lathe\Layout\Section;
use Lathe\Layout\Tab;

public function settings(): ?array
{
    return [
        Field::number('Interval', 'interval')->min(5)->rules('integer')->help('Seconds between checks.'),
        Section::make('Alerts', [
            Field::boolean('Notify when down', 'notify'),
        ]),
        Tab::group('Advanced', [
            Tab::make('Limits', [
                Field::number('Timeout', 'timeout')->min(1)->rules('integer'),
            ]),
        ]),
    ];
}
```

With `menu()` left at `null`, the sidebar shows a "Settings" link for the plugin.

Lathe serves the form from `GET /lathe/api/plugins/heartbeat/settings`, which returns the fields, the layout and the saved values. `PUT` to the same address validates with each field's rules and saves the values that the request carries. Both bypass `handleRequest()`. Other verbs answer `405`.

Values live in the `lathe_plugin_settings` table, one JSON row per plugin. Read and write them in your own code:

```php
$interval = $this->setting('interval', 30);

$this->saveSettings(['notify' => true]);
```

`saveSettings()` merges into the saved values, and a `null` value clears its key.

> Note: Settings are admin-facing values edited in the panel and stored in the database. The scaffold's `config/heartbeat.php` is developer-facing. Its defaults merge into `config('heartbeat')`, and a `config/heartbeat.php` in the app overrides them.

## Handle API requests

There are two ways to answer requests from the plugin's page.

**`handleRequest()`** answers every verb on `/lathe/api/plugins/{uriKey}/{path}`. Unknown paths and verbs throw a 404, which is what `parent::handleRequest()` does. Heartbeat answers `GET /lathe/api/plugins/heartbeat/status` this way.

**`routes()`** registers real Laravel routes. Lathe mounts them under `/lathe/api/plugins/{uriKey}/`, inside the panel API middleware, for users who may see the plugin. Package routes are registered before the catch-all, so they win. Heartbeat's `ping` route answers `GET /lathe/api/plugins/heartbeat/ping`.

Call either from the component with the `request` function of `@lathephp/core`:

```tsx
const status = await request<Status>(`${apiPath}/plugins/heartbeat/status`);
```

## Write the page

The page is a React component. It registers itself when the module loads:

```tsx
import { plugin, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Badge, Button, Card } from '@lathephp/ui';
import { useCallback, useEffect, useState } from 'react';

interface Status {
  app: string;
  php: string;
  laravel: string;
  database: string;
  time: string;
}

function Page() {
  const { apiPath } = useLathe();
  const { __ } = useTranslation();
  const [status, setStatus] = useState<Status | null>(null);

  const load = useCallback(
    () =>
      request<Status>(`${apiPath}/plugins/heartbeat/status`).then(
        (next) => {
          setStatus(next);
          return true;
        },
        () => false,
      ),
    [apiPath],
  );

  async function ping() {
    if (await load()) {
      toast.success(__('Heartbeat OK'));
    } else {
      toast.error(__('Heartbeat failed'));
    }
  }

  useEffect(() => {
    void load();
  }, [load]);

  return (
    <Card className="p-6">
      <h1 className="font-semibold">{__('Heartbeat')}</h1>
      <dl>
        <dt>{__('Application')}</dt>
        <dd>{status?.app}</dd>
        <dt>{__('PHP')}</dt>
        <dd>{status?.php}</dd>
        <dt>{__('Laravel')}</dt>
        <dd>{status?.laravel}</dd>
        <dt>{__('Database')}</dt>
        <dd>
          <Badge>{status?.database}</Badge>
        </dd>
        <dt>{__('Checked at')}</dt>
        <dd>{status?.time}</dd>
      </dl>
      <Button onClick={() => void ping()}>{__('Ping')}</Button>
    </Card>
  );
}

plugin('heartbeat', Page);
```

The key passed to `plugin()` must equal the plugin's `uriKey()`. See [Extension API](extension-api.md) for everything `@lathephp/core` exports.

## Test a plugin

The scaffold writes a Pest test on Lathe's test kit. The base `TestCase` extends `Lathe\Testing\TestCase` and registers the plugin:

```php
namespace Acme\Heartbeat\Tests;

use Acme\Heartbeat\Heartbeat;
use Lathe\Lathe;
use Lathe\Testing\TestCase as LatheTestCase;

abstract class TestCase extends LatheTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Lathe::registerPlugins(new Heartbeat);
    }
}
```

A test then calls the plugin's endpoints like any other route:

```php
it('answers ping from its package route', function () {
    $this->getJson('/lathe/api/plugins/heartbeat/ping')->assertOk()->assertExactJson(['pong' => true]);
});
```

Run the tests from the package folder with `composer test`.

## Resource plugins

A resource plugin renders a block on a Resource's detail page. Put it in `fields()` next to the fields. It never shows on a form or in the index:

```php
use App\Lathe\Plugins\StripeInspector;

public function fields(LatheRequest $request): array
{
    return [
        Field::text('Name'),

        StripeInspector::make(),
    ];
}
```

Generate one with `lathe:resource-plugin`:

```bash
php artisan lathe:resource-plugin StripeInspector
```

This writes `app/Lathe/Plugins/StripeInspector.php` and `resources/js/lathe/plugins/StripeInspector.tsx`, and adds the import to `boot.ts`. To put the class in a plugin package instead, pass the package name. Only the PHP class is written, because the package ships its own module:

```bash
php artisan lathe:resource-plugin StripeInspector --plugin=acme/stripe-inspector
```

### The class

A resource plugin extends `Lathe\Plugins\ResourcePlugin`. Its `name()` is the headline of the class name. Its `component()` is the kebab form, and the React component registers under that key.

`handleRequest(Request $request, string $path)` answers `/lathe/api/resource-plugins/{component}/{path}`. The request names the Resource and the record, in the `resource` and `resourceId` query parameters. Before your code runs, Lathe re-resolves the record through the Resource's scoped query and checks that the user may see the plugin, the Resource and this record. Call `resourceRecord($request)` to get that record. Never read an id from the query string yourself:

```php
namespace App\Lathe\Plugins;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lathe\Plugins\ResourcePlugin;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StripeInspector extends ResourcePlugin
{
    public function handleRequest(Request $request, string $path): Response
    {
        if ($path !== 'charges') {
            throw new NotFoundHttpException;
        }

        $customer = $this->resourceRecord($request);

        return new JsonResponse($this->chargesFor($customer));
    }
}
```

A user without access gets `403`. A record outside the Resource's scope gets `404`.

### The component

A resource plugin may return `assets()` like a plugin does. Lathe loads those scripts only on the detail pages that show the block. The component receives `resourceName` and `resourceId`. It registers with `resourcePlugin()`:

```tsx
import { request, resourcePlugin, useLathe } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function StripeInspector({ resourceName, resourceId }: { resourceName: string; resourceId: string }) {
  const { apiPath } = useLathe();
  const url = `${apiPath}/resource-plugins/stripe-inspector/charges?resource=${resourceName}&resourceId=${resourceId}`;
  // Fetch `url` with request() and draw the charges.

  return <Card className="p-6">{url}</Card>;
}

resourcePlugin('stripe-inspector', StripeInspector);
```

### Meta keys

Declare the keys you accept in `metaFields()`. A call to a declared key sets meta. A call to any other name throws `BadMethodCallException`, so a typo fails at once:

```php
protected function metaFields(): array
{
    return ['heading', 'maxCharges'];
}
```

```php
StripeInspector::make()->heading('Payments')->maxCharges(50)
```

Use `withMeta(array $meta)` for keys you did not declare. The values travel with the block in the record's data.

### Control who sees a block

A resource plugin supports `canSee()`:

```php
StripeInspector::make()->canSee(fn ($request) => $request->user()->hasRole('billing-admin'))
```

## What you see

After `lathe:plugin acme/heartbeat` and a build, the sidebar shows a Heartbeat section. Its page lists the application, PHP, Laravel, database and check time in five rows, and Ping shows the toast "Heartbeat OK". `php artisan lathe:plugins` lists Heartbeat with its class, version and status. A resource plugin shows as a block under the fields of a record's detail page.
