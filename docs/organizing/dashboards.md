---
title: Dashboards
description: Build dashboards of widget cards, register them, and set their name, icon, refresh button and access.
order: 1
---

# Dashboards

A dashboard is a named page of [widgets](widgets.md). It gives you a high-level view of the numbers that matter in your app. Lathe ships one dashboard, `Main`, and you can add as many more as you need.

## The Main dashboard

`Main` is the home page of the panel, at `/lathe`. It shows as "Dashboard" in the sidebar and has no widgets. `lathe:install` writes `app/Lathe/Dashboards/Main.php` for you. Fill in its `widgets()` method to give the home page some cards:

```php
<?php

namespace App\Lathe\Dashboards;

use App\Lathe\Widgets\NewUsers;
use App\Lathe\Widgets\OrdersPerDay;
use Lathe\Dashboards\Main as Dashboard;
use Lathe\Http\Requests\LatheRequest;

class Main extends Dashboard
{
    public function widgets(LatheRequest $request): array
    {
        return [
            NewUsers::make(),
            OrdersPerDay::make()->fullWidth(),
        ];
    }
}
```

> Note: `Main` overrides `name()` to return "Dashboard". Override `name()` again in your class to change it.

## Create a dashboard

Run the generator:

```bash
php artisan lathe:dashboard UserInsights
```

It writes `app/Lathe/Dashboards/UserInsights.php`. A dashboard extends `Lathe\Dashboards\Dashboard` and returns its widgets from `widgets()`:

```php
<?php

namespace App\Lathe\Dashboards;

use App\Lathe\Widgets\NewUsers;
use App\Lathe\Widgets\UsersByPlan;
use Lathe\Dashboards\Dashboard;
use Lathe\Http\Requests\LatheRequest;

class UserInsights extends Dashboard
{
    public function widgets(LatheRequest $request): array
    {
        return [
            NewUsers::make(),
            UsersByPlan::make(),
        ];
    }
}
```

Lathe calls `widgets()` only when someone opens this dashboard, so a heavy list does not slow the other pages.

## Register a dashboard

Register your dashboards in the `registerDashboards()` method of `app/Providers/LatheServiceProvider.php`:

```php
use App\Lathe\Dashboards\Main;
use App\Lathe\Dashboards\UserInsights;
use Lathe\Lathe;

protected function registerDashboards(): void
{
    Lathe::registerDashboards(Main::class, UserInsights::class);
}
```

`registerDashboards()` takes class names or instances. A registered dashboard appears in the sidebar under "Dashboards". If you register no dashboard with the key `main`, Lathe uses its built-in empty `Main`.

Two dashboards must not share a key. If they do, Lathe stops with an error that names both classes. Override `uriKey()` on one of them to fix it.

## Name and URL

The name comes from the class name. `UserInsights` becomes "User Insights". Override `name()` to change it:

```php
public function name(): string
{
    return 'User analytics';
}
```

The URL key is the kebab-case class name, so `UserInsights` lives at `/lathe/dashboards/user-insights`. Override the static `uriKey()` to change it. The key `main` is special: that dashboard lives at the panel home.

```php
public static function uriKey(): string
{
    return 'user-analytics';
}
```

## Description, icon and refresh button

A description shows under the dashboard title. An icon shows in the sidebar and beside the title. A refresh button reloads every card at once. Set each with a PHP attribute on the class:

```php
use Lathe\Attributes\Dashboard\Description;
use Lathe\Attributes\Dashboard\Icon;
use Lathe\Attributes\Dashboard\ShowRefreshButton;
use Lathe\Dashboards\Dashboard;
use Lathe\Enums\Icon as IconName;

#[Icon(IconName::Users)]
#[Description('Watch user growth and engagement.')]
#[ShowRefreshButton]
class UserInsights extends Dashboard
{
    // ...
}
```

Or use the matching methods when you register the dashboard:

```php
Lathe::registerDashboards(
    UserInsights::make()
        ->icon(IconName::Users)
        ->description('Watch user growth and engagement.')
        ->showRefreshButton(),
);
```

| Attribute | Method | Effect |
|-----------|--------|--------|
| `#[Icon(Icon)]` | `icon(Icon $icon)` | The sidebar and title icon |
| `#[Description(string)]` | `description(string $text)` | The line under the title |
| `#[ShowRefreshButton]` | `showRefreshButton()` | A button that reloads every card |

## The range

The header holds one range picker, and every card on the dashboard shows the range it picks. It offers 7 days, 30 days and 12 months, opens on 30 days, and keeps the choice in the URL as `?range=365`. Override `ranges()` to offer other day counts:

```php
public function ranges(): array
{
    return [7 => '7 days', 90 => '90 days'];
}
```

A card shows the picked range when its own `ranges()` has that key. A card without it shows its default range and says which one, such as "Last 30 days" beside a value card's number. Return an empty array to hide the picker.

## The sidebar entry

Each dashboard adds its own link to the sidebar through its `menu()` method. Override it to change that link, for example to add a badge:

```php
use Lathe\Http\Requests\LatheRequest;
use Lathe\Menu\MenuItem;

public function menu(LatheRequest $request): MenuItem
{
    return parent::menu($request)->badge('New');
}
```

[Menus](menus.md) covers badges and the rest of the sidebar.

## Authorization

Hide a dashboard from some users with `canSee()` or `canSeeWhen()`:

```php
Lathe::registerDashboards(
    Main::make(),
    UserInsights::make()->canSee(fn ($request) => $request->user()->is_admin),
    Finance::make()->canSeeWhen('viewFinance'),
);
```

`canSeeWhen()` checks a Gate ability for the signed-in panel user. A dashboard the user may not see leaves the sidebar, and opening its URL by hand is refused.

## What you see

After you register a dashboard, the sidebar lists it under "Dashboards" with its icon. Opening it shows its title, the optional description line, and a grid of cards that fill in as their numbers arrive. With the refresh button on, each card shows a loading skeleton and then its new value when you click it.
