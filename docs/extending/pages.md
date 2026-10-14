---
title: Pages
description: Add a standalone screen to the panel with a PHP class and a React component.
order: 4
---

# Pages

A page is a screen of your own inside the panel: a report, a settings console, a view of another service. It is not tied to a Resource. A page is a small PHP class and a React component. Lathe serves it at its own URL and you link to it from the sidebar.

> Note: A page is the lightest way to add a screen. A [plugin](plugins.md) can add routes, settings, Resources and dashboards, and it can ship as a Composer package. Use a page for a screen that lives in your app. Use a plugin when you need more or want to share it.

## Create a page

```bash
php artisan lathe:page Reports
```

The command writes two files and one line:

- `app/Lathe/Pages/Reports.php`, the page class.
- `resources/js/lathe/pages/Reports.tsx`, the React component.
- An import for the component in `resources/js/lathe/boot.ts`.

The first React-bearing generator you run also creates `resources/js/lathe/`, `boot.ts`, a `vite.lathe.config.ts` of its own, and adds `@lathephp/core`, `@lathephp/ui` and `@lathephp/vite-plugin` to your `package.json`. Install the packages and build the extensions:

```bash
pnpm install
pnpm lathe:build
```

[Frontend](frontend.md) explains the build and the `lathe:build` and `lathe:dev` scripts. `--force` replaces an existing page class.

## The page class

A page extends `Lathe\Pages\Page`:

```php
namespace App\Lathe\Pages;

use Lathe\Pages\Page;

class Reports extends Page
{
    public function name(): string
    {
        return 'Reports';
    }
}
```

Lathe serves the page at `/lathe/pages/{uriKey}`. This one is at `/lathe/pages/reports`.

You may override these members:

| Member | Default | Purpose |
|---|---|---|
| `name(): string` | The headline of the class name | The title of the page |
| `static uriKey(): string` | The kebab form of the class name | The URL segment, and the key the component registers under |
| `icon(?Icon $icon = null): ?Icon` | `null` | An `Icon` for the page. With an argument it sets the icon; without one it reads it |
| `authorize(Request $request): bool` | Runs `canSee()` | Who may open the page |
| `path(): string` | `pages/{uriKey}` | The panel path of the page |

For example, to give the page another URL key:

```php
public static function uriKey(): string
{
    return 'monthly-reports';
}
```

The page is then at `/lathe/pages/monthly-reports`, and its component registers as `monthly-reports`.

## Register a page

Register pages in `boot()` of `app/Providers/LatheServiceProvider.php`:

```php
use App\Lathe\Pages\Reports;
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::registerPages(Reports::class);
}
```

`registerPages()` takes class names or instances. Two different page classes with the same `uriKey()` stop the boot with an exception that names both.

## Link to a page

A registered page is not in the sidebar until you link to it. Add a menu item that points at `pages/{uriKey}`:

```php
use Illuminate\Http\Request;
use Lathe\Enums\Icon;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;

Menu::mainMenu(function (Request $request, Menu $menu) {
    $menu->add(
        MenuSection::section('Insights', [
            MenuItem::link('Reports', 'pages/reports')->icon(Icon::BarChart),
        ]),
    );
});
```

`MenuSection::page('reports')` is a shorter form. It makes a section titled after the key whose title links to the page. See [Menus and navigation](../organizing/menus.md).

## The React component

The generated component is a plain function component. It receives the page's props and registers itself with `page()` when the module loads:

```tsx
import { page } from '@lathephp/core';
import type { PageProps } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function Reports({ pageKey, basePath, path }: PageProps) {
  return (
    <Card className="p-6">
      <h3 className="font-semibold leading-none tracking-tight">Reports</h3>
      {/* Your page content */}
    </Card>
  );
}

page('reports', Reports);
```

`PageProps` gives the component what it needs to fetch data and build links:

| Prop | Type | Description |
|---|---|---|
| `pageKey` | `string` | The page's URI key, such as `reports` |
| `basePath` | `string` | The full path of the page, such as `/lathe/pages/reports`, for building links |
| `path` | `string \| undefined` | The rest of the URL after the page key. It is an empty string when there is none |

The key given to `page()` must match the page's `uriKey()`. A page has no API of its own. To load data, call a [plugin](plugins.md) route or one of your own routes with `request()` from `@lathephp/core`.

After you add or change a component, run `pnpm lathe:build`, or keep `pnpm lathe:dev` running while you work.

### Sub-paths

A page may use the rest of its URL. `/lathe/pages/reports/2026-06` gives the component `path` as `2026-06`. Use it for client-side routing or to choose what to fetch.

## Control who sees a page

Restrict a page when you register it:

```php
Lathe::registerPages(
    Reports::make()->canSee(fn ($request) => $request->user()->isAdmin()),
);
```

`canSeeWhen('view-reports')` checks a Gate ability instead. For more complex rules, override `authorize()` on the class:

```php
use Illuminate\Http\Request;

public function authorize(Request $request): bool
{
    return $request->user()?->can('view-reports') ?? false;
}
```

A user who may not see a page gets the `403` page. An unknown page address gets `404`. Hide the menu item too, with `->canSeeWhen('view-reports')` on the `MenuItem`.

## What you see

After you register the page, link it and build the extensions, the sidebar shows your entry. Opening it shows the page at `/lathe/pages/reports` inside the panel shell, with your component in the content area. A user who fails the page's check sees the 403 page.
