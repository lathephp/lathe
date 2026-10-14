---
title: Notifications
description: Send stored notifications to panel users, show them in the bell, and deliver them through more channels.
order: 8
---

# Notifications

Lathe has a notification center. A bell in the header lists the notifications of the signed-in user, with links and buttons, and lets the user mark them read or delete them. Notifications are stored with Laravel's [database notification channel](https://laravel.com/docs/notifications#database-notifications), so they survive a reload and reach a user who was away.

A notification is not a toast. A toast is a short message in the corner of the page that disappears after four seconds. To show one from your own component, see [Extension API](extension-api.md#show-toasts).

## Set up

The `notifications` table comes with the migrations that `lathe:install` publishes and runs. The user model must use Laravel's `Notifiable` trait:

```php
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;
}
```

Three keys in `config/lathe.php` control the center:

```php
'notifications' => [
    'enabled' => true,
    'polling_interval' => 7,
    'show_unread_count' => false,
],
```

| Key | Default | Effect |
|---|---|---|
| `enabled` | `true` | Shows the bell. When `false` there is no bell and no polling, and the notification endpoints answer `404`. |
| `polling_interval` | `7` | Seconds between checks for new notifications. It must be at least 1. |
| `show_unread_count` | `false` | Shows the number of unread notifications on the bell. By default the bell shows a dot. |

## Send a notification

`Lathe\Notifications\LatheNotification` is a builder on top of Laravel's `Notification`. Build one and send it with `notify()`:

```php
use Lathe\Notifications\LatheNotification;

$user->notify(
    (new LatheNotification)
        ->success()
        ->message('Invoice paid: $99.99')
        ->body('Payment was processed successfully.')
        ->actionButton('View invoice', '/lathe/resources/invoices/42')
);
```

The text is plain text. Lathe does not read it as HTML.

### Types

`success()`, `error()`, `warning()` and `info()` set the type and a matching icon in one call. `info()` is the default. `type('success')` does the same as `success()`.

| Method | Type | Icon |
|---|---|---|
| `success()` | `success` | `Icon::CheckCircle` |
| `error()` | `error` | `Icon::CircleX` |
| `warning()` | `warning` | `Icon::AlertTriangle` |
| `info()` | `info` | `Icon::Info` |

Call `icon(Icon|string $icon)` after the type to use another icon. See [Icons](icons.md).

### Message and body

`message()` is the main line and `body()` is the detail below it. Both take a string or a `Stringable`:

```php
(new LatheNotification)
    ->info()
    ->message('Export complete.')
    ->body('3,200 rows exported in 4 seconds.')
```

### Actions

A notification may carry links and buttons. There are three styles:

```php
use Lathe\Enums\Icon;

(new LatheNotification)
    ->success()
    ->message('Report ready.')
    ->action('View report', '/lathe/pages/reports')                          // a text link
    ->actionButton('Download', '/reports/42/download', Icon::Download)       // a small button
    ->openInNewTab()                                                          // applies to the last action
    ->actionIcon('Share', '/reports/42/share', Icon::Share)                  // an icon button, the text is its tooltip
```

`openInNewTab()` applies to the action added last, and does nothing when there is none. `actions(NotificationAction ...$actions)` adds actions you built yourself:

```php
use Lathe\Notifications\NotificationAction;

->actions(
    NotificationAction::make('Details', '/lathe/resources/exports/7'),
    NotificationAction::button('Download', $url, Icon::Download)->openInNewTab(),
    NotificationAction::iconButton('Share', $shareUrl, Icon::Share),
)
```

An action URL must be relative, `http` or `https`. Any other scheme, such as `javascript:`, throws an `InvalidArgumentException`.

### Duration

`duration(int $milliseconds)` sets how long the toast of a new notification stays on screen:

```php
(new LatheNotification)->success()->message('Saved.')->duration(3000)
```

## Write a notification class

For a notification you send often, or one that needs a queue, extend `LatheNotification` and fill it in the constructor:

```php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lathe\Enums\Icon;
use Lathe\Notifications\LatheNotification;

class InvoicePaid extends LatheNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(float $amount, int $invoiceId)
    {
        $this->success()
            ->message('Invoice paid: $'.number_format($amount, 2))
            ->body('Payment was processed successfully.')
            ->actionButton('View invoice', '/lathe/resources/invoices/'.$invoiceId, Icon::FileText);
    }
}
```

```php
$user->notify(new InvoicePaid(99.99, 42));
```

## What is stored

`toArray()` returns what Lathe stores in the `data` column and reads back for the bell:

| Key | Type | Description |
|---|---|---|
| `type` | `string` | `info`, `success`, `warning` or `error` |
| `icon` | `string` | The Lucide name of the icon |
| `message` | `string` | The main text |
| `body` | `string` | Optional detail text |
| `actions` | `array` | The actions. Each has `text`, `url`, `icon`, `style` and `openInNewTab` |
| `duration` | `int` | Optional milliseconds |

`style` is `link`, `button` or `icon`. The bell shows any database notification of the user. A key it does not find takes a default, so notifications from other packages show too.

## Built-in notifications

Lathe sends two notifications itself, both built with `LatheNotification`:

- **Export ready.** When a queued export finishes, the user gets "Your export is ready for download." with a Download button that opens a new tab. If the export fails, the message is "Your export failed."
- **Import complete.** When an import finishes, the user gets the counts of rows created, updated, skipped and failed. If rows failed, a button downloads them as a CSV.

See [Import and export](../taking-action/import-export.md).

## Send through more channels

Every `LatheNotification` goes to the `database` channel. Add other channels, such as `mail` or a channel class of your own, with `Lathe::notificationChannels()` in `boot()` of a service provider:

```php
use App\Notifications\Channels\SlackChannel;
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::notificationChannels('mail', SlackChannel::class);
}
```

Each argument is a Laravel channel name or a custom channel class. Duplicates collapse. The list is fixed once the app has booted, so a later call throws a `LogicException`.

The channels apply to every `LatheNotification`, the built-in ones too. A channel formats the notification the usual way, so the notification must support it. The `toArray()` payload serves a custom channel. For `mail`, extend `LatheNotification` and add a `toMail()` method.

## Manage notifications in the bell

The bell opens a panel where the user can:

- read all notifications, newest first (the 100 most recent);
- mark one notification read or unread, or mark all read;
- delete one notification or all of them.

The panel polls a cheap count every `polling_interval` seconds, and loads the list again only when the count rises or the panel opens. When the unread count rises while the panel is closed, a toast shows the newest notification with its actions. A user only ever reads and changes their own notifications.

## What you see

After you send a notification, the bell in the header shows a dot, or the unread number when `show_unread_count` is on, within one polling interval. A toast shows the message. Opening the bell lists the notification with its icon, message, detail, age and buttons, and the user can mark it read or delete it. With `notifications.enabled` set to `false` the bell is gone.
