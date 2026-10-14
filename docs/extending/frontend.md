---
title: Frontend
description: Register scripts and styles, build your own React components, and pass data from PHP to the browser.
order: 5
---

# Frontend

The panel is a React app that Lathe ships already built. Your app needs Node only to build the components you add yourself: custom fields, filters, widgets, pages, plugins and action modals. Lathe gives those components their own build, so they never touch the build of your main frontend.

Your components import from two packages, `@lathephp/core` and `@lathephp/ui`. The panel loads them once and shares them with every extension through an import map, so your code and the panel use the same copy of React.

## Register assets

### Scripts and styles

Register a script or a stylesheet that the panel loads on every page. Give it a name and a file path:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::script('analytics', public_path('vendor/analytics/analytics.js'));
    Lathe::style('analytics', public_path('vendor/analytics/analytics.css'));
}
```

Put this in `boot()` of `app/Providers/LatheServiceProvider.php`. A name registered twice keeps the last path. A script is an ES module, so it can import `@lathephp/core`. Lathe serves the file at `/lathe/modules/{name}-{hash}.js`, and only registered files resolve.

Called while a panel request is served, for example in a plugin's `serving()`, the registration lasts for that request only.

A path that starts with `https://` is a remote asset. The panel loads it from that address and adds its origin to the `script-src` or `style-src` of the response's Content Security Policy:

```php
Lathe::script('widget-cdn', 'https://cdn.example.com/widget.js');
```

> Note: A plugin does not call `Lathe::script()`. It returns its module from `assets()`. See [Plugins](plugins.md) and [Plugin distribution](plugin-distribution.md).

### Publish assets for production

Lathe serves local scripts and styles itself. To serve them as static files, for example from a CDN, copy them to the public folder:

```bash
php artisan lathe:assets
```

Each local script and style lands in `public/vendor/lathe/plugins/` as `{name}.js` or `{name}.css`. Remote assets are skipped. Pass `--force` to replace files that exist. Run the command on deploy, so a plugin update reaches the public copy.

## The extension build

The first generator that writes a React component sets up the build once. It skips this when `resources/js/lathe/boot.ts` exists. It writes:

```text
resources/js/lathe/
├── boot.ts        # the entry: one import per component
├── fields/
├── filters/
├── widgets/
└── plugins/
vite.lathe.config.ts
```

It also adds `@lathephp/core`, `@lathephp/ui` and `@lathephp/vite-plugin` to the dev dependencies of your `package.json`, and prints the install command for your package manager. It never edits your `vite.config.ts`.

Each component file registers itself when it loads, and the generator appends one import line to `boot.ts`:

```ts
import './fields/Currency';
import './filters/PriceRange';
```

`vite.lathe.config.ts` calls the Vite plugin in app mode:

```ts
import { lathe } from '@lathephp/vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [lathe({ mode: 'app' })],
});
```

Add two scripts to `package.json`:

```json
"scripts": {
  "lathe:build": "vite build --config vite.lathe.config.ts",
  "lathe:dev": "vite --config vite.lathe.config.ts"
}
```

`lathe({ mode: 'app' })` reads `resources/js/lathe/boot.ts`. Pass `bootFile` to use another entry.

### Develop

```bash
pnpm lathe:dev
```

This starts a Vite dev server and writes `public/vendor/lathe/extensions/hot`. While that file exists, the panel loads your boot file from the dev server. A change reloads the page. React Fast Refresh does not apply, because your components render with the panel's React. Stopping the server removes the file.

### Build for production

```bash
pnpm lathe:build
```

This writes hashed ES modules and a `manifest.json` to `public/vendor/lathe/extensions/`. The panel loads the entry named in the manifest, together with any CSS your boot file imports. Run the build on deploy, or commit the folder.

`php artisan lathe:doctor` fails when a PHP field, filter, widget or page names a component key that no plugin and no extension registers. It reads the keys from the string literal in each registration call.

## Custom fields

`lathe:field` writes the PHP class:

```bash
php artisan lathe:field Currency
```

By default it extends `Field`. Use `--extends` to extend a built-in type and keep its casting, validation and formatting:

```bash
php artisan lathe:field Percentage --extends=number
```

The allowed values are `field`, `text`, `textarea`, `number`, `boolean`, `date`, `datetime`, `code`, `badge`, `status`, `file` and `image`.

Add `--custom` to also write a React component and its import:

```bash
php artisan lathe:field Currency --extends=number --custom
```

This writes `app/Lathe/Fields/Currency.php` and `resources/js/lathe/fields/Currency.tsx`. The class sets a component key:

```php
class Currency extends NumberField
{
    protected string $component = 'currency';
}
```

The component registers under the same key. A field takes a renderer for each place it shows: `form`, `index` and `detail`. Pass a bare component to register only the form input:

```tsx
import { field } from '@lathephp/core';
import type { DetailFieldProps, FieldProps, IndexFieldProps } from '@lathephp/core';

function CurrencyForm({ field: definition, value, onChange, id, describedBy, invalid, disabled }: FieldProps) {
  return (
    <input
      id={id}
      value={typeof value === 'string' ? value : ''}
      onChange={(event) => onChange(event.target.value)}
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      disabled={disabled}
      placeholder={definition.placeholder ?? undefined}
    />
  );
}

function CurrencyIndex({ cell }: IndexFieldProps) {
  return <span>{cell.display}</span>;
}

function CurrencyDetail({ field: shown }: DetailFieldProps) {
  return <span>{shown.display}</span>;
}

field('currency', { form: CurrencyForm, index: CurrencyIndex, detail: CurrencyDetail });
```

A form input calls `onChange(value)` with the new value and puts `id`, `describedBy`, `invalid` and `disabled` on its input. The label, help text and error message belong to the panel. A built-in field keeps its own renderers for its key.

## Custom filters

```bash
php artisan lathe:filter PriceRange --custom
```

This writes `app/Lathe/Filters/PriceRange.php` and `resources/js/lathe/filters/PriceRange.tsx`. Without `--custom` the command writes a PHP-only filter of the type you give with `--type` (`select`, `boolean`, `date` or `range`).

A custom filter's key ends in `-filter`. The command above registers `price-range-filter`, and the PHP class returns the same key from `component()`:

```tsx
import { filter } from '@lathephp/core';
import type { FilterProps } from '@lathephp/core';

function PriceRange({ filter: definition, value, onChange }: FilterProps) {
  return (
    <label>
      {definition.name}
      <input value={typeof value === 'string' ? value : ''} onChange={(event) => onChange(event.target.value)} />
    </label>
  );
}

filter('price-range-filter', PriceRange);
```

## Custom widgets

```bash
php artisan lathe:widget TeamActivity --type=custom
```

This writes `app/Lathe/Widgets/TeamActivity.php`, a `CustomWidget`, and the component `resources/js/lathe/widgets/TeamActivity.tsx`. The other widget types use components the panel already has. See [Widgets](../organizing/widgets.md).

```tsx
import { widget } from '@lathephp/core';
import type { WidgetProps } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function TeamActivity({ widget: card }: WidgetProps) {
  return <Card className="p-6">{card.name}</Card>;
}

widget('team-activity', TeamActivity);
```

## Pages, plugins and action modals

A page registers with `page()`, a plugin with `plugin()` and an action's modal with `actionModal()`. The generators `lathe:page`, `lathe:plugin --inline` and `lathe:resource-plugin` write the component and its import for you. See [Pages](pages.md), [Plugins](plugins.md) and [Extension API](extension-api.md).

## Pass data from PHP to the browser

Give the page values with `Lathe::provideToScript()`:

```php
Lathe::provideToScript([
    'mapboxToken' => config('services.mapbox.token'),
]);
```

Read them in a component with `config()` from `@lathephp/core`:

```tsx
import { config } from '@lathephp/core';

const token = config('mapboxToken') as string;
```

Called at boot, in a service provider, the values are global. Called while a panel request is served, for example in a plugin's `serving()`, they belong to that request. A value may be a closure that takes the `Illuminate\Http\Request`, and Lathe calls it for each request. `config()` returns `undefined` for a key nobody provided.

Do not put secrets in these values. They reach every signed-in user's browser.

## Customize the generators

The generators render from stubs. Publish them with `php artisan lathe:stubs` and edit your copies. See [Stubs](stubs.md).

## What you see

After `pnpm lathe:build`, your custom fields, filters and widgets draw in the panel where their key is used. With `pnpm lathe:dev` running, saving a component file reloads the panel. `php artisan lathe:assets` prints one line for each registered local asset, saying whether it copied the file.
