---
title: Menus and navigation
description: Add your own sections, groups, links and badges to the sidebar, and put items in the user menu.
order: 3
---

# Menus and navigation

Lathe builds the sidebar for you. It lists your dashboards, then your Resources grouped by their `group`, then the menus of your plugins. When you want more control, you add your own sections, groups and links with the classes in `Lathe\Menu`.

## How the sidebar is built

From top to bottom the sidebar holds:

1. The dashboards the user may see, under "Dashboards".
2. A "Navigation" section for the loose items and groups you add.
3. Your own sections, then the sections of your plugins.
4. One section for each Resource `group`. Resources without a group go under "Resources". Resources sort by label inside a section.
5. The user menu in the footer.

A target appears once. If you add a menu item for a Resource or dashboard that Lathe would list anyway, your item wins and the automatic one is dropped. To remove a Resource from the sidebar, set `->displayInNavigation(false)` on its definition. To replace its default entry, override the static `menu()` method of the Resource and return a `MenuItem`.

## Customize the main menu

Call `Menu::mainMenu()` in the `boot()` method of `app/Providers/LatheServiceProvider.php`, after `parent::boot()`. The callback receives the request and the `Menu`. Add entries with `$menu->add()`. The value the callback returns is ignored:

```php
use Illuminate\Http\Request;
use Lathe\Enums\Icon;
use Lathe\Menu\Menu;
use Lathe\Menu\MenuGroup;
use Lathe\Menu\MenuItem;
use Lathe\Menu\MenuSection;

public function boot(): void
{
    parent::boot();

    Menu::mainMenu(function (Request $request, Menu $menu) {
        $menu->add(
            MenuSection::section('Customers', [
                MenuItem::resource('customers'),
                MenuItem::resource('orders'),
            ])->icon(Icon::Users)->collapsible(),

            MenuSection::section('Content', [
                MenuItem::resource('posts'),
                MenuItem::resource('categories'),
            ])->icon(Icon::FileText),
        );
    });
}
```

`Menu::using()` does the same as `Menu::mainMenu()`. The menus of authorized plugins are always added before your callback runs.

`add()` and `append()` put entries at the end. `prepend()` puts them at the start. All three take one or more entries:

```php
$menu->prepend(MenuItem::link('Home', ''));
$menu->append(MenuItem::externalLink('Status page', 'https://status.example.com'));
```

An item or group that is not inside a section goes into the "Navigation" section.

## Menu sections

`MenuSection` is a titled block of links and groups:

```php
MenuSection::section('Customers', [
    MenuItem::resource('customers'),
    MenuItem::resource('orders'),
]),

// A section whose title links to a Resource:
MenuSection::resource('customers'),

// A section whose title links to a page:
MenuSection::page('system-status'),
```

These methods change a section:

```php
MenuSection::section('Content', [/* ... */])
    ->icon(Icon::FileText)
    ->collapsible()
    ->sort(1);
```

- `icon()` sets the icon.
- `path('dashboards/sales')` makes the title a link to a path inside the panel. A collapsible section can have one too.
- `collapsible()` adds a chevron that folds the section.
- `collapsedByDefault()` starts it folded. It also makes the section collapsible. A section that holds the open page stays open.
- `sort()` sets the order. Lower numbers come first, the default is 9999, and equal numbers keep the order you added them in.
- `item()` adds one more item or group.

## Menu groups

`MenuGroup` puts items under a sub-heading, inside a section or in the menu itself:

```php
MenuSection::section('Business', [
    MenuGroup::group('Licensing', [
        MenuItem::dashboard('sales'),
        MenuItem::resource('licenses'),
    ])->icon(Icon::Key)->collapsible(),

    MenuGroup::group('Customers', [
        MenuItem::resource('customers'),
    ])->collapsedByDefault(),
]),
```

A group takes `icon()`, `collapsible()`, `collapsedByDefault()`, `item()`, `sort()` and the badge and access methods below.

## Menu items

`MenuItem` is one link. Resources, dashboards and lenses are named by their URL key, which is `uriKey()` of the class. For a class `Order` that is `orders`. The panel adds its own path in front.

```php
MenuItem::resource('orders'),
MenuItem::dashboard('sales'),
MenuItem::lens('orders', 'pending-orders'),
MenuItem::plugin(Analytics::class, 'reports'),
MenuItem::link('Cashier', 'cashier'),
MenuItem::link('Billing portal', '/billing'),
MenuItem::externalLink('Documentation', 'https://example.com/docs'),
```

- `resource()` links to the Resource index, and `dashboard()` to a dashboard. A registered dashboard supplies its own name and icon.
- `lens()` links straight to a lens. A third argument sets the label.
- `plugin()` links to the page of a plugin. The optional second argument is a sub path.
- `link()` takes a path inside the panel. A path that starts with a slash is used as a full URL path.
- `externalLink()` links to another site and adds `rel="noopener noreferrer"`.

Other methods:

```php
MenuItem::externalLink('Documentation', 'https://example.com/docs')
    ->icon(Icon::FileText)
    ->openInNewTab()
    ->sort(5);
```

`target('_blank')` is what `openInNewTab()` sets.

### Active state

An item shows as active when the current URL starts with its path. So `resources/orders` stays active on an order's detail page. Override that when you need to:

```php
MenuItem::link('Home', '')->active(),

MenuItem::link('Reports', 'reports')
    ->activeWhen(fn ($request) => $request->is('*/reports*')),

MenuItem::link('Reports', 'reports')
    ->activeUnless(fn ($request) => $request->is('*/reports/archived')),
```

## Authorization

Items, groups and sections can all be hidden:

```php
MenuItem::link('Cashier', 'cashier')
    ->canSee(fn ($request) => $request->user()->is_admin),

MenuItem::resource('orders')
    ->canSeeWhen('viewAny', Order::class),
```

`canSeeWhen()` checks a Gate ability for the signed-in panel user.

## Badges

A badge is a small label beside a menu entry, such as a count. Give an entry a badge with `badge()`. The second argument is the type:

```php
MenuItem::resource('issues')->badge('New', 'success'),
```

For more control, build a `Badge`:

```php
use Lathe\Menu\Badge;

MenuItem::resource('issues')->badge(Badge::warning('3')->tooltip('3 issues need review')),
```

`Badge::info()`, `Badge::success()`, `Badge::warning()` and `Badge::destructive()` each set the type. `new Badge('Beta', 'info')` and `type()` set it by hand. The constants `Badge::INFO`, `Badge::SUCCESS`, `Badge::WARNING` and `Badge::DESTRUCTIVE` name the four types.

| Type | Use it for |
|------|------------|
| `info` | Neutral facts |
| `success` | Good states |
| `warning` | Things that need a look |
| `destructive` | Errors and overdue work |

### Counts that need a query

A badge value may be a closure. The closure runs each time the menu is built, which is on every page. For a count that reads the database, use `withBadge()` on the item instead. It runs the closure once and keeps the result for 60 seconds for each user:

```php
MenuItem::resource('issues')->withBadge(
    fn () => Issue::where('status', 'open')->count(),
    ttlSeconds: 30,
    type: Badge::DESTRUCTIVE,
),
```

A TTL of 0 runs the closure on every request. If the closure throws, Lathe reports the error and leaves the badge off, and the page still loads. `withBadge()` works on menu items. For a section or group, use `badge()`.

## The user menu

The user menu opens from the user tile in the sidebar footer. It shows the name and email, your items, a "Security" link when Fortify has features that give the user something to change, the appearance switcher and "Log out". Add your own items with `Menu::userMenu()`:

```php
Menu::userMenu(function (Request $request, Menu $menu) {
    $menu->prepend(
        MenuItem::link('My profile', 'resources/users/'.$request->user()->getKey()),
    );

    if ($request->user()->subscribed()) {
        $menu->append(MenuItem::externalLink('Billing', 'https://billing.example.com'));
    }
});
```

Use `MenuItem` only. The logout link cannot be removed.

## What you see

After you customize the menu, the sidebar shows your sections and groups with their icons, in the order you set. Collapsible sections have a chevron, badges sit at the right edge of their entry, and the item for the page you are on is highlighted. The user tile in the footer opens a menu with your extra items above "Log out".
