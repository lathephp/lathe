---
title: Icons
description: The icon cases you can give to Resources, dashboards, menus, tabs, widgets and notifications.
order: 10
---

# Icons

Where Lathe takes an icon, it takes a `Lathe\Enums\Icon` case. A case gives you autocompletion in your editor and a PHP error at the typo, not a blank square in the panel.

```php
use Lathe\Enums\Icon;

MenuItem::resource('posts')->icon(Icon::FileText);
```

> Note: Resources are discovered when the app boots. A case that does not exist stops the whole panel, not only the Resource that used it: `Error: Undefined constant Lathe\Enums\Icon::Document`. Check the name in the table below. There is no `Icon::Document` (use `Icon::FileText`) and no `Icon::CurrencyDollar` (use `Icon::DollarSign`).

The cases follow the [Lucide](https://lucide.dev) icon set. A case is the PascalCase form of a Lucide name, and its value is the kebab-case name. Where an API takes a string, pass the value: `Icon::ShieldCheck->value` is `'shield-check'`.

## Where icons are used

These take an `Icon` case:

```php
// Menu sections, groups and items
MenuSection::section('Content', [...])->icon(Icon::FileText);
MenuItem::link('Reports', 'pages/reports')->icon(Icon::BarChart);

// A dashboard
$dashboard->icon(Icon::LayoutDashboard);

// A value widget and a metric table row
$widget->icon(Icon::ShoppingCart);

// A layout tab
Tab::make('Billing', [...])->icon(Icon::CreditCard);

// A notification, and its action buttons
(new LatheNotification)->actionButton('Download', $url, Icon::Download);
```

On a dashboard class, the `#[Icon]` attribute has the same name as the enum, so alias the enum:

```php
use Lathe\Attributes\Dashboard\Icon;
use Lathe\Enums\Icon as IconName;

#[Icon(IconName::LayoutDashboard)]
class Main extends Dashboard {}
```

These take the Lucide name as a string, so you may pass an enum value or any Lucide name:

```php
$definition->icon(Icon::ShoppingCart->value);   // a Resource's sidebar icon
(new SendWelcomeEmail)->icon('mail');            // an action
Field::badge('Status')->icons(['active' => 'check']); // field icons draw a smaller set, see Fields
```

## All icons

All 156 cases, in alphabetical order:

| Case | Lucide name |
|---|---|
| `Icon::Activity` | `activity` |
| `Icon::AlertCircle` | `alert-circle` |
| `Icon::AlertTriangle` | `alert-triangle` |
| `Icon::Archive` | `archive` |
| `Icon::ArrowDown` | `arrow-down` |
| `Icon::ArrowLeft` | `arrow-left` |
| `Icon::ArrowRight` | `arrow-right` |
| `Icon::ArrowUp` | `arrow-up` |
| `Icon::AtSign` | `at-sign` |
| `Icon::Award` | `award` |
| `Icon::Banknote` | `banknote` |
| `Icon::Ban` | `ban` |
| `Icon::BarChart` | `bar-chart-3` |
| `Icon::BellRing` | `bell-ring` |
| `Icon::Bell` | `bell` |
| `Icon::Bookmark` | `bookmark` |
| `Icon::BookOpen` | `book-open` |
| `Icon::Box` | `box` |
| `Icon::Briefcase` | `briefcase` |
| `Icon::Bug` | `bug` |
| `Icon::Building` | `building-2` |
| `Icon::CalendarDays` | `calendar-days` |
| `Icon::Calendar` | `calendar` |
| `Icon::Camera` | `camera` |
| `Icon::CheckCircle` | `check-circle` |
| `Icon::Check` | `check` |
| `Icon::CircleDot` | `circle-dot` |
| `Icon::CircleX` | `circle-x` |
| `Icon::Circle` | `circle` |
| `Icon::Clipboard` | `clipboard` |
| `Icon::Clock` | `clock` |
| `Icon::Cloud` | `cloud` |
| `Icon::Code` | `code` |
| `Icon::Cog` | `cog` |
| `Icon::Coins` | `coins` |
| `Icon::Contact` | `contact` |
| `Icon::Copy` | `copy` |
| `Icon::Cpu` | `cpu` |
| `Icon::CreditCard` | `credit-card` |
| `Icon::Crown` | `crown` |
| `Icon::Database` | `database` |
| `Icon::DollarSign` | `dollar-sign` |
| `Icon::Download` | `download` |
| `Icon::Edit` | `edit` |
| `Icon::ExternalLink` | `external-link` |
| `Icon::EyeOff` | `eye-off` |
| `Icon::Eye` | `eye` |
| `Icon::FileCode` | `file-code` |
| `Icon::FilePlus` | `file-plus` |
| `Icon::Files` | `files` |
| `Icon::FileText` | `file-text` |
| `Icon::File` | `file` |
| `Icon::Filter` | `filter` |
| `Icon::Fingerprint` | `fingerprint` |
| `Icon::Flag` | `flag` |
| `Icon::Flame` | `flame` |
| `Icon::FolderOpen` | `folder-open` |
| `Icon::FolderPlus` | `folder-plus` |
| `Icon::Folder` | `folder` |
| `Icon::Gauge` | `gauge` |
| `Icon::Gift` | `gift` |
| `Icon::Globe` | `globe` |
| `Icon::Grid` | `grid` |
| `Icon::HardDrive` | `hard-drive` |
| `Icon::Hash` | `hash` |
| `Icon::Heart` | `heart` |
| `Icon::HelpCircle` | `help-circle` |
| `Icon::Home` | `home` |
| `Icon::Hourglass` | `hourglass` |
| `Icon::Image` | `image` |
| `Icon::Inbox` | `inbox` |
| `Icon::Info` | `info` |
| `Icon::Key` | `key` |
| `Icon::Languages` | `languages` |
| `Icon::Laptop` | `laptop` |
| `Icon::Layers` | `layers` |
| `Icon::LayoutDashboard` | `layout-dashboard` |
| `Icon::LifeBuoy` | `life-buoy` |
| `Icon::Lightbulb` | `lightbulb` |
| `Icon::Link` | `link` |
| `Icon::List` | `list` |
| `Icon::Lock` | `lock` |
| `Icon::LogOut` | `log-out` |
| `Icon::Mail` | `mail` |
| `Icon::MapPin` | `map-pin` |
| `Icon::Map` | `map` |
| `Icon::Megaphone` | `megaphone` |
| `Icon::MessageCircle` | `message-circle` |
| `Icon::MessageSquare` | `message-square` |
| `Icon::Mic` | `mic` |
| `Icon::Monitor` | `monitor` |
| `Icon::Moon` | `moon` |
| `Icon::Music` | `music` |
| `Icon::Navigation` | `navigation` |
| `Icon::Newspaper` | `newspaper` |
| `Icon::Package` | `package` |
| `Icon::Palette` | `palette` |
| `Icon::Paperclip` | `paperclip` |
| `Icon::PenTool` | `pen-tool` |
| `Icon::Percent` | `percent` |
| `Icon::Phone` | `phone` |
| `Icon::PieChart` | `pie-chart` |
| `Icon::Pin` | `pin` |
| `Icon::Play` | `play` |
| `Icon::Plus` | `plus` |
| `Icon::Power` | `power` |
| `Icon::Printer` | `printer` |
| `Icon::QrCode` | `qr-code` |
| `Icon::Radio` | `radio` |
| `Icon::Receipt` | `receipt` |
| `Icon::RefreshCw` | `refresh-cw` |
| `Icon::Repeat` | `repeat` |
| `Icon::Rocket` | `rocket` |
| `Icon::RotateCcw` | `rotate-ccw` |
| `Icon::Save` | `save` |
| `Icon::Scan` | `scan` |
| `Icon::ScrollText` | `scroll-text` |
| `Icon::Search` | `search` |
| `Icon::Send` | `send` |
| `Icon::Server` | `server` |
| `Icon::Settings` | `settings` |
| `Icon::Share` | `share` |
| `Icon::ShieldCheck` | `shield-check` |
| `Icon::Shield` | `shield` |
| `Icon::ShoppingBag` | `shopping-bag` |
| `Icon::ShoppingCart` | `shopping-cart` |
| `Icon::SlidersHorizontal` | `sliders-horizontal` |
| `Icon::Smartphone` | `smartphone` |
| `Icon::Sparkles` | `sparkles` |
| `Icon::Star` | `star` |
| `Icon::Store` | `store` |
| `Icon::Sun` | `sun` |
| `Icon::Table` | `table` |
| `Icon::Tag` | `tag` |
| `Icon::Target` | `target` |
| `Icon::Terminal` | `terminal` |
| `Icon::ThumbsDown` | `thumbs-down` |
| `Icon::ThumbsUp` | `thumbs-up` |
| `Icon::Timer` | `timer` |
| `Icon::ToggleLeft` | `toggle-left` |
| `Icon::Trash` | `trash` |
| `Icon::TrendingDown` | `trending-down` |
| `Icon::TrendingUp` | `trending-up` |
| `Icon::Truck` | `truck` |
| `Icon::Unlock` | `unlock` |
| `Icon::Upload` | `upload` |
| `Icon::UserCheck` | `user-check` |
| `Icon::UserPlus` | `user-plus` |
| `Icon::Users` | `users` |
| `Icon::UserX` | `user-x` |
| `Icon::User` | `user` |
| `Icon::Video` | `video` |
| `Icon::Wallet` | `wallet` |
| `Icon::Wifi` | `wifi` |
| `Icon::Wrench` | `wrench` |
| `Icon::Zap` | `zap` |

## What you see

The icon you name goes with the menu entry, widget, tab or action that carries it. A case that is not in the table throws a PHP `Error` before the panel renders.
