---
title: Localization
description: Translate the panel into another language, load your own translations, and set the locale per user.
order: 9
---

# Localization

Every string the panel shows can be translated: buttons, messages, menu entries and the labels you give your Resources. Lathe uses the English text as the translation key and reads translations from JSON files. Without a translation, the English text shows.

## How translation works

Strings pass through `Lathe::__()`. It looks the key up in the catalogue of the current locale and returns it, or returns the key when there is no translation:

```php
use Lathe\Lathe;

Lathe::__('Name');                              // 'Name', or its translation
Lathe::__('Welcome, :name!', ['name' => 'Ada']); // fills the placeholder
```

The third argument is a default to use when the key is missing: `Lathe::__('Name', [], 'Title')`.

## Create a translation file

`lathe:translate` copies Lathe's English catalogue, which holds every key the panel uses, to your app:

```bash
php artisan lathe:translate es
```

This writes `lang/vendor/lathe/es.json`. Translate the values and leave the keys. Lathe finds the file on the next request. There is nothing to register. Pass `--force` to replace a file that exists.

A locale can be a language (`es`) or a language with a region (`fr-CA`; `fr_CA` reads the same). Lathe builds the catalogue in layers: English first, then the language, then the full locale. For `fr-CA` it reads `en`, then `fr`, then `fr-CA`, and in each step the app's `lang/vendor/lathe/` file overrides the package's file. A region file only needs the strings that differ from its language.

```json
{
    "Name": "Nombre",
    "Create :resource": "Crear :resource",
    "Welcome, :name!": "¡Bienvenido, :name!",
    "Action was executed successfully.": "La acción se ejecutó correctamente."
}
```

## Load translations from code

To read a file that Lathe does not look for, call `Lathe::loadTranslations()` in `boot()` of `app/Providers/LatheServiceProvider.php`. It takes a file path or an array and merges it over the catalogue:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::loadTranslations(lang_path('panel/es.json'));

    Lathe::loadTranslations([
        'Name' => 'Nombre',
        'Email' => 'Correo electrónico',
    ]);
}
```

Translations loaded at boot apply to every request and every locale. Loaded while a panel request is served, for example in a plugin's `serving()`, they apply to that request only.

To work with the catalogue directly, resolve `Lathe\Support\TranslationManager` from the container. Its `set($key, $value)`, `has($key)`, `get($key)` and `all()` methods read and change the catalogue:

```php
use Lathe\Support\TranslationManager;

app(TranslationManager::class)->set('Custom label', 'Etiqueta personalizada');

app(TranslationManager::class)->has('Custom label'); // true
```

## Placeholders

A `:placeholder` in a string is replaced by the value you pass. The casing of the token sets the casing of the value:

| Token | Result for `john` |
|---|---|
| `:name` | `john` |
| `:Name` | `John` |
| `:NAME` | `JOHN` |

```php
Lathe::__('Welcome, :Name!', ['name' => 'john']); // Welcome, John!
```

When two placeholders share a prefix, such as `:resource` and `:resources`, Lathe fills the longer name first.

## Set the locale of a user

By default the panel uses `app()->getLocale()`. To follow each user's own setting, call `Lathe::userLocale()` in `boot()` with a closure that takes the request and returns a locale:

```php
use Illuminate\Http\Request;
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::userLocale(fn (Request $request) => $request->user()?->locale);
}
```

A `null` or empty answer falls back to the app locale. Lathe sets the app locale to the answer for the panel request, uses it to pick the catalogue, and sends it to the browser, where dates and numbers use it for their format. Return a tag the browser knows, such as `en-US` or `de-DE`, when the format matters.

For languages written right to left, see `Lathe::enableRTL()` in [Layout](../organizing/layout.md).

## Translate your Resources

Strings you write in PHP are not translated until you pass them through `Lathe::__()`.

**Resource labels.** Override `label()` and `singularLabel()` on the Resource:

```php
use Lathe\Lathe;

public static function label(): string
{
    return Lathe::__('Posts');
}

public static function singularLabel(): string
{
    return Lathe::__('Post');
}
```

**Button labels.** `createButtonLabel()` and `updateButtonLabel()` return "Create :resource" and "Update :resource" through the catalogue already. Translate those two keys in the JSON file, or override the methods:

```php
public static function createButtonLabel(): string
{
    return Lathe::__('Publish Post');
}
```

**Fields and relations.** Pass a translated label as the first argument:

```php
Field::text(Lathe::__('Email Address'), 'email_address')
Field::hasMany(Lathe::__('Posts'), 'posts')
```

**Filters, lenses and actions.** Override `name()`:

```php
public function name(): string
{
    return Lathe::__('User Type');
}
```

Then add the English text and its translation to the JSON file:

```json
{
    "Post": "Publicación",
    "Posts": "Publicaciones",
    "User Type": "Tipo de usuario"
}
```

## Translations in the browser

The panel sends the catalogue of the current locale to the browser with the page. A component reads it with `useTranslation()` from `@lathephp/core`, so a string you translate in PHP is also translated in your React code:

```tsx
import { useTranslation } from '@lathephp/core';

const { __ } = useTranslation();

__('Order #:id is on hold.', { id: 12 });
```

Pass the English text as the key. See [Extension API](extension-api.md#hooks).

## Translations in plugins

A plugin loads its translations in `boot()`, which runs once:

```php
use Lathe\Lathe;

public function boot(): void
{
    parent::boot();

    Lathe::loadTranslations([
        'Heartbeat OK' => 'Heartbeat correcto',
        'Heartbeat failed' => 'Heartbeat fallido',
    ]);
}
```

Load a whole file the same way, with `Lathe::loadTranslations(__DIR__.'/../lang/es.json')`. Keep `serving()` for state that depends on the request.

## What you see

After `php artisan lathe:translate es` and a user whose locale is `es`, the panel shows the Spanish text for each key you translated, and the English text for each key you left out. A Resource with `label()` wrapped in `Lathe::__()` shows its translated name in the sidebar and headings.
