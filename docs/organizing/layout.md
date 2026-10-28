---
title: Layout and theming
description: Group fields into sections, tabs and grids, then set the panel's theme style, colours, dark mode, sidebar and text direction.
order: 5
---

# Layout and theming

This page has two halves. The first groups the fields of a Resource into sections, tabs and grids. The second changes how the whole panel looks: its theme style, colours, dark mode, layout and text direction.

## Sections, tabs and grids

A long list of fields is hard to read. Lathe has three layout pieces for the detail page and the create and edit forms. A `Section` is a titled card of fields. A `Tab` group switches between views. A `Grid` puts those side by side. You put them in `fields()` next to your fields:

```php
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Layout\Section;

public function fields(LatheRequest $request): array
{
    return [
        Field::id(),

        Section::make('Profile', [
            Field::text('Full name'),
            Field::date('Birthday'),
            Field::text('Place of birth'),
        ]),
    ];
}
```

Fields outside any section go into a default section called "Details". A field attribute may appear only once on a page. If it appears twice, Lathe stops with an error.

## Sections

`Section::make()` takes the name, a list of fields and, as an optional third argument, an attribute key. The methods below change how a section looks.

### Limit the visible fields

`limit()` shows that many fields and hides the rest behind a "Show all" link:

```php
Section::make('Profile', [
    Field::text('Full name'),
    Field::date('Birthday'),
    Field::text('Place of birth'),
    Field::textarea('Bio'),
])->limit(2),
```

### Collapsible sections

`collapsible()` adds a button that folds the section. `collapsedByDefault()` starts it folded and makes it collapsible too. `collapsed()` is the same method under another name:

```php
Section::make('Profile', [/* ... */])->collapsible(),
Section::make('History', [/* ... */])->collapsedByDefault(),
```

### The toolbar

The first section of a page holds the toolbar with the edit and action buttons. `withToolbar()` puts it on a section of your choice:

```php
Section::make('Profile', [/* ... */])->withToolbar(),
```

### Headings

A heading sits above its card. `hideHeading()` removes it. `headingInCard()` moves it inside the card, above a divider, which keeps multi-column grids even:

```php
Section::make('Notes', [/* ... */])->hideHeading(),
Section::make('Timeline', [/* ... */])->headingInCard(),
```

When you change the heading of the first section, Lathe moves the toolbar to its own bar above the layout, so the buttons stay visible.

`help()` writes a line of text under the heading. It takes HTML, which Lathe cleans before it sends it:

```php
Section::make('Billing', [/* ... */])
    ->help('The customer\'s <strong>payment</strong> details.'),
```

### Attribute key

A section's key comes from its name, so "Billing" becomes `billing`. Fields refer to a section by that key. Set the key by hand when two sections share a name. Do it with the third argument or with `withAttribute()`:

```php
Section::make('Billing', [/* ... */], 'billing-details'),
Section::make('Billing', [/* ... */])->withAttribute('billing-details'),
```

### Columns, span and border

`columns()` lays the fields of a section out in 1 to 6 columns on screens 768 pixels wide and up. `borderless()` removes the lines between fields:

```php
Section::make('Address', [
    Field::text('Street'),
    Field::text('City'),
    Field::text('State'),
    Field::text('Zip code'),
])->columns(2)->borderless(),
```

Inside a grid, `columnSpan()` sets how many of the grid's columns the section covers.

## Tabs

Tabs split a page into views, such as Details, Settings and Relations. Create a group with `Tab::group()` and put `Tab::make()` objects in it:

```php
use Lathe\Layout\Tab;

public function fields(LatheRequest $request): array
{
    return [
        Field::id(),

        Tab::group('Event', [
            Tab::make('Purchases', [
                Field::currency('Price'),
                Field::number('Tickets available'),
            ]),

            Tab::make('Venue', [
                Field::text('Venue name'),
            ]),
        ]),
    ];
}
```

Pass `null`, or leave out the label, for a group with no title above it. Use a named argument for the tabs:

```php
Tab::group(tabs: [
    Tab::make('Orders', [/* ... */]),
    Tab::make('Tickets', [/* ... */]),
]),
```

### Tab icons, badges and access

A tab can show an icon, a badge and a check for who may see it:

```php
use Lathe\Enums\Icon;

Tab::make('Issues', [/* ... */])
    ->icon(Icon::Tag)
    ->badge(fn () => Issue::where('status', 'open')->count())
    ->badgeType('danger'),

Tab::make('Admin settings', [/* ... */])
    ->canSee(fn ($request) => $request->user()->is_admin),
```

The badge is a string or a closure. The badge type is `info`, `success`, `warning` or `danger`, and any other value throws an error. `columns()` and `borderless()` work on a tab as they do on a section.

### Sections inside tabs

A tab takes fields, sections and grids:

```php
Tab::make('Details', [
    Section::make('Personal', [
        Field::text('Name'),
        Field::email('Email'),
    ]),
    Section::make('Address', [
        Field::text('Street'),
        Field::text('City'),
    ])->columns(2),
]),
```

### Group options

A tab group has more options:

```php
Tab::group('Settings', [/* ... */])
    ->activeTab(1)
    ->persistTab()
    ->vertical()
    ->contained(false)
    ->columnSpan(2);
```

- `activeTab()` picks the tab that is open first, by its position. The first tab is 0, and it is the default.
- `persistTab()` keeps the open tab in the URL as `?tab=<slug>`, so a reload or the Back button returns to it. Pass a name to use another query key. The URL carries it only when you call this.
- `vertical()` puts the tab list at the start edge.
- `contained(false)` draws the tabs edge to edge, with no card around them.
- `columnSpan()` sets the width of the group inside a grid.

You can also build a group in steps with `label()`, `tab()` and `tabs()`.

On a form, a tab that holds a field with an error shows a dot. After a failed save, the first such tab opens.

## Grids

A `Grid` puts sections and tab groups side by side, for example a main area and a sidebar:

```php
use Lathe\Layout\Grid;

Grid::make(3)->schema([
    Section::make('Details', [
        Field::text('Name'),
        Field::email('Email'),
    ])->columnSpan(2),

    Section::make('Sidebar', [
        Field::text('Role'),
        Field::text('Status'),
    ])->columnSpan(1),
]),
```

`Grid::make()` takes the number of columns, 1 to 6, and 2 by default. On a screen narrower than 768 pixels a grid becomes one column. A span wider than its grid throws an error. Grids hold sections, tab groups and other grids:

```php
Grid::make(3)->schema([
    Tab::group('Content', [
        Tab::make('Body', [/* ... */]),
        Tab::make('SEO', [/* ... */]),
    ])->columnSpan(2),

    Section::make('Meta', [/* ... */])->columnSpan(1),
]),
```

## Index tabs

The tabs above group fields. A different kind of tab filters the rows on the index page. A Resource returns those from `tabs()`, using `Lathe\Tabs\Tab`:

```php
use Lathe\Http\Requests\LatheRequest;
use Lathe\Tabs\Tab;

public function tabs(LatheRequest $request): array
{
    return [
        Tab::make('All'),

        Tab::make('Published')
            ->modifyQueryUsing(fn ($query) => $query->where('status', 'published'))
            ->withCount(),

        Tab::make('Flagged')
            ->modifyQueryUsing(fn ($query) => $query->where('flagged', true))
            ->badge(fn () => Post::where('flagged', true)->count())
            ->badgeType('warning')
            ->icon('flag'),
    ];
}
```

The first tab is the default view. `withCount()` shows the row count as a badge, and `canSee()` hides a tab from some users. Users can also save their own views. See [Saved tabs](../querying/saved-tabs.md).

## Theme style

The panel ships eight looks, called styles. Pick one with `theme.style` in `config/lathe.php`. Only the style you pick loads in the browser:

```php
'theme' => [
    'style' => 'nova',
],
```

The styles are `nova` (the default), `vega`, `lyra`, `maia`, `mira`, `luma`, `rhea` and `sera`.

An unknown name fails the check in `php artisan lathe:doctor`. `theme.radius` sets the base corner radius as a CSS length. Its default is `0.5rem`.

## Dark mode

Every user can pick Light, Dark or System in the user menu. The choice is kept in the browser. `theme.appearance` sets what a user sees before they have chosen. It is `light`, `dark` or `system`, and `system` follows the setting of the operating system:

```php
'theme' => [
    'appearance' => 'dark',
],
```

The panel applies the choice before the first paint, so a reload does not flash.

## Colours

`theme.colors` changes single colours without touching the style. Each key is a colour token, and each value is a CSS colour string, or an array with a `light` and a `dark` value. A `null` keeps the colour of the style:

```php
'theme' => [
    'colors' => [
        'primary' => 'oklch(0.55 0.2 260)',
        'primary-foreground' => 'oklch(0.98 0 0)',
        'destructive' => [
            'light' => 'oklch(0.58 0.22 27)',
            'dark' => 'oklch(0.7 0.19 22)',
        ],
    ],
],
```

The config file lists all 56 tokens. They are `background`, `foreground`, `card`, `popover`, `primary`, `secondary`, `muted`, `accent`, `destructive`, `success`, `warning` and `info` (each with a `-foreground` partner), `border`, `input`, `ring`, `link`, `link-hover`, `chart-1` to `chart-5`, the `sidebar` tokens, and the 16 design system tokens below. Charts and widgets use the `chart-*` tokens. A value that is not a colour, or an unknown token, fails `php artisan lathe:doctor`.

With no colours set, the panel wears Lathe's copper theme: warm greys, a copper primary button and copper marks, in light and dark. Every text colour reads at 4.5:1 on its ground, and every mark at 3:1.

The design system tokens:

- `brand`: copper for marks that carry no text, such as the active menu bar and `chart-1`. Text on a copper fill uses `primary`.
- `focus`: the focus ring.
- `primary-subtle`, `success-subtle`, `warning-subtle`, `info-subtle` and `destructive-subtle`: the quiet fills of badges and banners. Each has a text partner: `primary-subtle-foreground`, `success-subtle-foreground`, `warning-subtle-foreground`, `info-subtle-foreground` and `destructive-subtle-foreground`.
- `skeleton`: the fill of loading placeholders.
- `scrim`: the shade behind a dialog or the phone menu.
- `chart-other`: the "Other" slice of a chart.
- `sidebar-hover`: a menu item under the pointer.

Set one like any other token. This makes the success badge a deeper green and keeps its text readable:

```php
'theme' => [
    'colors' => [
        'success-subtle' => [
            'light' => 'oklch(0.95 0.05 150)',
            'dark' => 'oklch(0.3 0.06 150)',
        ],
        'success-subtle-foreground' => [
            'light' => 'oklch(0.4 0.12 150)',
            'dark' => 'oklch(0.85 0.1 150)',
        ],
    ],
],
```

When you set any colour of your own, or ran `lathe:preset`, the panel keeps your look and wears no copper. Each of the 40 tokens you did not set keeps the neutral value it had before the copper theme, and the new tokens follow your palette:

- `brand` and `focus` take `primary`, made darker or lighter where needed until they read at 3:1 on `background`.
- Each `-subtle` is a 14% tint of its colour over `background`. Its `-foreground` is the colour, darker on a light background or lighter on a dark one, until it reads at 4.5:1 on the tint.
- `skeleton` takes `muted`, `chart-other` takes `muted-foreground`, and `sidebar-hover` takes `sidebar-accent`.
- `scrim` keeps its default.

A token you set yourself wins. A colour with a `light` and a `dark` value gives a light and a dark result.

The panel also defines `shadow-xs`, `shadow-sm`, `shadow-md` and `shadow-lg`, the shadows of buttons, cards, menus and dialogs, which your CSS can read and override.

Each colour token is also a utility colour, such as `bg-success-subtle` or `text-brand`.

`theme.font` swaps the typeface. The default is Archivo, which the package carries in its latin and latin-ext subsets with its width and weight axes, so the browser fetches nothing from another site. Page and record titles use its wide 122% width. To use your own font, name the family and list woff2 files under your `public` folder:

```php
'theme' => [
    'font' => [
        'family' => 'Acme Sans',
        'fallback' => 'sans-serif',
        'files' => [
            ['url' => '/fonts/acme.woff2', 'weight' => 400, 'style' => 'normal'],
        ],
    ],
],
```

A family with no files must be a system font stack.

## Apply a preset

`lathe:preset` copies a design from [shadcn](https://ui.shadcn.com) into the `theme` block of `config/lathe.php`. Give it a preset code:

```bash
php artisan lathe:preset a5ONTE
```

It sets the style, the radius, the font and the colours. Use `--interactive` to choose each part in turn. `--show` prints the current theme without changing it. `--reset` sets every colour to `null`. The command asks the shadcn website for the preset, so it needs a network connection. If the preset has a font, add its files to `theme.font.files`, or the panel uses the fallback stack.

## Sidebar, header and content

The `layout` block shapes the page frame:

```php
'layout' => [
    'sidebar' => [
        'collapsible' => 'icon',
        'default_open' => true,
        'width' => '16rem',
        'width_icon' => '3rem',
    ],
    'header' => [
        'sticky' => true,
        'search' => true,
    ],
    'content' => [
        'width' => 'full',
    ],
],
```

- `layout.sidebar.collapsible` is `icon` to shrink to icons, `offcanvas` to slide out of view, or `none` to keep it open.
- `layout.sidebar.default_open` sets whether the sidebar starts open.
- `layout.sidebar.width` and `width_icon` set the open and the collapsed widths.
- `layout.header.sticky` keeps the header in view while you scroll, and `layout.header.search` shows the search box.
- `layout.content.width` is `full`, or `contained` for a narrower column.

Tables have their own two keys. `table.density` is `compact`, `comfortable` or `spacious` and sets the row height. `table.striped` shades every other row.

## Right to left

For languages written right to left, call `Lathe::enableRTL()` in the `boot()` method of `app/Providers/LatheServiceProvider.php`:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::enableRTL();
}
```

Pass a closure to decide for each request. The closure receives the request and returns a bool:

```php
Lathe::enableRTL(fn ($request) => in_array(app()->getLocale(), ['ar', 'he', 'fa'], true));
```

The sidebar then moves to the right edge, text flows from right to left, and arrows and chevrons flip. `Lathe::isRTL()` tells you whether the current request is right to left.

## Your own CSS

To change something the config cannot reach, register a stylesheet with `Lathe::style()`. It loads on every panel page. Parts of the page carry `data-lathe-*` attributes, such as `data-lathe-sidebar`, `data-lathe-header`, `data-lathe-table` and `data-lathe-widget`, so your rules can find them:

```php
Lathe::style('brand', resource_path('css/lathe-brand.css'));
```

## What you see

After you group fields, the detail and form pages show titled cards, tab strips and side-by-side columns. After you change the theme, the whole panel takes the new style, colours and corner radius, and the user menu switches between light and dark. With right-to-left on, the sidebar sits on the right and the text runs right to left.
