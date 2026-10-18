---
title: Plugin distribution
description: Build a plugin into one ES module, ship it as a Composer package, and see how the panel loads it.
order: 2
---

# Plugin distribution

A plugin you publish reaches apps you have never seen. Those apps do not run your build, so the plugin carries a compiled JavaScript module. You build it with `@lathephp/vite-plugin` in plugin mode. The module shares the panel's copy of React and of `@lathephp/core`, so it stays small and its hooks work inside the panel.

The two builds are separate:

| | App extension | Distributed plugin |
|---|---|---|
| Built by | Your app (`pnpm lathe:build`) | The plugin package (`pnpm build`) |
| Vite plugin call | `lathe({ mode: 'app' })` | `lathe({ mode: 'plugin', name, entry })` |
| Entry | `resources/js/lathe/boot.ts` | The `entry` you name, such as `resources/js/plugin.tsx` |
| Output | Hashed modules and `manifest.json` in `public/vendor/lathe/extensions` | `dist/{name}.js`, an optional CSS file and `dist/lathe-plugin.json` |
| Imports Lathe from | `@lathephp/core` and `@lathephp/ui` | The same two packages |

Both import the same packages, so a component moves between them without changes. `lathe:plugin vendor/package` writes the plugin side for you. This page explains it so you can keep or rewrite it.

## The entry

The entry imports from `@lathephp/core` and registers its components as it loads. It has no default export:

```tsx
import { plugin } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function Page() {
  return <Card className="p-6">Heartbeat</Card>;
}

plugin('heartbeat', Page);
```

Import UI from `@lathephp/ui`, and everything else the panel offers from `@lathephp/core`. Do not import from Base UI.

## The build

The scaffold's `vite.config.ts` calls the Vite plugin in plugin mode, next to the React plugins:

```ts
import { lathe } from '@lathephp/vite-plugin';
import babel from '@rolldown/plugin-babel';
import react, { reactCompilerPreset } from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [
    lathe({ mode: 'plugin', name: 'heartbeat', entry: 'resources/js/plugin.tsx' }),
    react(),
    babel({ presets: [reactCompilerPreset()] }),
  ],
});
```

`name` is the asset name your PHP class registers. The build sets these for you:

- The output is one ES2022 file, `dist/heartbeat.js`, with no chunks and no hash in the name. Lathe hashes the file when it serves it. A stylesheet imported by the entry comes out as `dist/heartbeat.css`.
- These imports stay external: `react`, `react/jsx-runtime`, `react/compiler-runtime`, `react-dom`, `react-dom/client`, `@lathephp/core`, `@lathephp/ui` and every `@lathephp/ui/*` subpath. The panel's import map answers them at run time.
- The build fails if code from `react` or `react-dom` ends up in the output. A second copy of React would break hooks.
- In a CSS file, `@import "tailwindcss"` becomes the theme and utilities layers only. The panel already holds the base styles and the colour values.
- It writes `dist/lathe-plugin.json`:

```json
{
  "name": "heartbeat",
  "core": "^1.0",
  "scripts": ["heartbeat.js"],
  "styles": [],
  "components": { "plugin": ["heartbeat"] }
}
```

`components` lists the key of every registration call in your entry (`field`, `filter`, `widget`, `plugin`, `page`, `resourcePlugin` and `actionModal`), read from the first argument of each call. Write the keys as string literals so the build can find them. `core` is the range of `@lathephp/core` the module needs.

Build from the package folder:

```bash
pnpm install && pnpm build
```

## Ship the module

Declare the module from the plugin's `assets()` method. Lathe registers it when the plugin registers, even if you override `boot()`:

```php
public function assets(): array
{
    return [
        'scripts' => ['heartbeat' => __DIR__.'/../dist/heartbeat.js'],
        'styles' => ['heartbeat' => __DIR__.'/../dist/heartbeat.css'],
    ];
}
```

Leave `styles` out when the plugin has none. You do not call `Lathe::script()` or `Lathe::style()` for a plugin.

> Note: Apps install your plugin with Composer and never run its build, so `dist/` must be in the tagged release. The scaffold keeps `dist/` out of its `.gitignore` for this reason: build, then commit `dist/` before you tag.

## How the panel loads it

The panel page carries an import map that points `react`, `@lathephp/core`, `@lathephp/ui` and each `@lathephp/ui/<name>` subpath at the panel's own files. Every script tag carries the page's CSP nonce.

Lathe serves a plugin script at `/lathe/modules/{name}-{hash}.js` and a stylesheet at `/lathe/styles/{name}-{hash}.css`. The hash is the first 8 characters of the file's SHA-256 digest. Only files that a plugin registered resolve. Any other name answers `404`. Responses carry `Cache-Control: public, max-age=31536000, immutable`, so a new build gets a new URL.

The panel imports all plugin modules before the first render, so every registration has run when the page paints. A module that fails to load or takes longer than 10 seconds logs `[lathe] plugin "name" failed to load`, shows one toast, and the rest of the panel keeps working. For explicit ordering, wrap registrations in `booting()` from `@lathephp/core`.

To serve the files statically, copy them to the public folder:

```bash
php artisan lathe:assets
```

This copies each registered local script and style to `public/vendor/lathe/plugins/`. Pass `--force` to replace copies that exist. Run it on every deploy, after `composer install`.

A remote asset is an `https://` URL given to `Lathe::script()` or `Lathe::style()`, or returned from `assets()`. Lathe adds its origin to the `script-src` or `style-src` of that response's Content Security Policy, and `lathe:assets` skips it.

## The npm packages

Three packages support plugin authors. Add them as dev dependencies. The scaffold's `package.json` already does.

| Package | What it is |
|---|---|
| `@lathephp/core` | The browser API: registration, requests, hooks, toasts and types. See [Extension API](extension-api.md). |
| `@lathephp/ui` | The UI kit: Lathe's wrappers of shadcn components. |
| `@lathephp/vite-plugin` | The `lathe()` Vite plugin for plugin and app builds. |

They carry the same version as `lathe/lathe`. At run time `@lathephp/core` and `@lathephp/ui` throw if you bundle them by mistake, so a wrong build fails at once.

`@lathephp/ui` exports the everyday components from its main entry: Accordion, Alert, AlertDialog, Avatar, Badge, Button, Card, Checkbox, Dialog, DropdownMenu, Input, Kbd, Label, Popover, Progress, RadioGroup, Select, Separator, Sheet, Skeleton, Slider, Spinner, Switch, Table, Tabs, Textarea and Tooltip, with their parts and variant helpers such as `buttonVariants`. Heavier components load on demand from a subpath, for example `import { Calendar } from '@lathephp/ui/calendar'`. The subpaths are `breadcrumb`, `button-group`, `calendar`, `chart`, `code-editor`, `collapsible`, `combobox`, `command`, `direction`, `empty`, `field`, `hover-card`, `input-group`, `item`, `otp-field`, `pagination`, `popover`, `scroll-area`, `select`, `shimmer-bar`, `sidebar`, `toast`, `toggle`, `toggle-group`.

## Test the module

`@lathephp/core/testing` exports the whole API of `@lathephp/core` without the guard that throws outside the panel, plus `mockLathe()` to stand in for the panel's shared data. Point the `@lathephp/core` alias of your Vitest config at it:

```ts
import { defineConfig } from 'vitest/config';

export default defineConfig({
  resolve: {
    alias: { '@lathephp/core': '@lathephp/core/testing' },
  },
});
```

```tsx
import { mockLathe } from '@lathephp/core/testing';
import { beforeEach } from 'vitest';

beforeEach(() => {
  mockLathe({ basePath: '/lathe' });
});
```

## Compatibility

The `require` of your `composer.json` names `lathe/lathe` with a range, such as `^1.0`. The scaffold fills in the installed major version. `php artisan lathe:doctor` reports a plugin when the installed Lathe is outside that range, and when the `core` range in `dist/lathe-plugin.json` does not match the installed `@lathephp/core`. `php artisan lathe:plugins --diagnose` shows the same checks.

## Build a plugin that lives in the app

A plugin package in `lathe-components/` builds with the app. List it in the `composer.json` of the package with `extra.lathe.entry`, set to the entry path:

```json
"extra": {
    "lathe": {
        "plugin": "Acme\\Heartbeat\\Heartbeat",
        "entry": "resources/js/plugin.tsx"
    }
}
```

`pnpm lathe:build` and `pnpm lathe:dev` of the app then build each such path-repository plugin to its `dist/`, and `lathe:dev` rebuilds it when files change. Turn this off with `lathe({ mode: 'app', autoDiscover: false })`. In production the plugin's committed `dist/` is used.

## Route order

A plugin's `routes()` are mounted under `/lathe/api/plugins/{uriKey}/` before the plugin API catch-all, so a package route wins over `handleRequest()` for the same path. See [Plugins](plugins.md#handle-api-requests).

## Publish

A distributed plugin is a normal Composer package. It names its class under `extra.lathe.plugin`:

```json
"extra": {
    "lathe": {
        "plugin": "Acme\\Heartbeat\\Heartbeat"
    }
}
```

The scaffold's `lathe-components/README.md` lists the steps to move a plugin out of the project: split the folder into its own repository, tag a release and publish it on Packagist or on your own Composer repository. Apps then install it with one command:

```bash
composer require acme/heartbeat
```

Discovery registers the plugin and the committed `dist/` carries the page. The app calls no registration method and runs no build.

## What you see

After `pnpm build` in the plugin folder, `dist/` holds `heartbeat.js` and `lathe-plugin.json`. In an app that has the plugin installed, the page source lists the import map before any module script, the sidebar shows the plugin, and its page opens. A plugin whose module fails to load shows one toast, and the rest of the panel still works.
