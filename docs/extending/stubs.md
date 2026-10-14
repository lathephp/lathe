---
title: Stubs
description: The generator commands that write Lathe classes, the stubs they render, and how to change them.
order: 11
---

# Stubs

Lathe writes the classes and components you need from templates called stubs. Each generator is an Artisan command. It writes into `app/Lathe/`, in the namespace of your app, or into a plugin package. You may publish the stubs and edit them, so your team's classes start the way you want.

## The generators

| Command | Writes | Where |
|---|---|---|
| `lathe:resource` | A Resource and its policy | `app/Lathe/Resources`, `app/Policies` |
| `lathe:action` | An action | `app/Lathe/Actions` |
| `lathe:filter` | A filter | `app/Lathe/Filters` |
| `lathe:lens` | A lens | `app/Lathe/Lenses` |
| `lathe:widget` | A widget | `app/Lathe/Widgets` |
| `lathe:dashboard` | A dashboard | `app/Lathe/Dashboards` |
| `lathe:field` | A field class | `app/Lathe/Fields` |
| `lathe:page` | A page and its React component | `app/Lathe/Pages` |
| `lathe:resource-plugin` | A resource plugin and its React component | `app/Lathe/Plugins` |
| `lathe:plugin` | A plugin package, or with `--inline` an in-app plugin and its React component | `lathe-components/`, or `app/Lathe/Plugins` |

Every generator takes the class name as its argument. The name may hold letters, digits and underscores, and each part starts with a letter. A `/` makes a subfolder and a sub-namespace: `lathe:resource Billing/Invoice` writes `app/Lathe/Resources/Billing/Invoice.php` in the namespace `App\Lathe\Resources\Billing`. These names are reserved because they would clash with Lathe's own classes: `Action`, `Dashboard`, `Field`, `Filter`, `Lens`, `Plugin`, `Resource` and `Widget`.

A generator refuses to overwrite a file. Pass `--force` to replace it. `--force` replaces the file without asking, so any change in the old file is lost.

```bash
php artisan lathe:resource Post --force
```

`--plugin=vendor/package` writes the class into a plugin package instead of the app. It works for `lathe:resource`, `lathe:action`, `lathe:lens`, `lathe:dashboard` and `lathe:resource-plugin`. Lathe looks for the package in `lathe-components/` first and then in `vendor/`, and uses the namespace of its `src/` folder. `lathe:resource` writes no policy then.

```bash
php artisan lathe:action Notify --plugin=acme/price-tracker
```

[Commands](../getting-started/commands.md) lists every flag.

## Resources

```bash
php artisan lathe:resource Post
```

This writes `app/Lathe/Resources/Post.php`, a Resource for the model `App\Models\Post`, and `app/Policies/PostPolicy.php` unless it exists. If the model does not exist yet, the command prints a warning. See [Defining resources](../your-first-resource/defining-resources.md).

## Actions, lenses and dashboards

```bash
php artisan lathe:action SendWelcomeEmail
php artisan lathe:lens MostValuableUsers
php artisan lathe:dashboard Analytics
```

Each writes a class with the methods you fill in.

## Filters

```bash
php artisan lathe:filter UserType
```

By default this is a select filter. `--type` picks another: `select`, `boolean`, `date` or `range`. `--custom` writes a filter class and a React component instead:

```bash
php artisan lathe:filter PriceRange --custom
```

This writes `app/Lathe/Filters/PriceRange.php` and `resources/js/lathe/filters/PriceRange.tsx`, and adds the import to `boot.ts`. The component key gets a `-filter` suffix, here `price-range-filter`. The PHP class returns the same key from `component()`.

## Widgets

```bash
php artisan lathe:widget NewUsers --type=trend
```

`--type` is `value` (the default), `trend`, `partition`, `progress`, `table`, `bar`, `pie`, `radar`, `radial`, `help` or `custom`. Only `custom` also writes a React component.

## Fields

```bash
php artisan lathe:field Currency
```

This writes `app/Lathe/Fields/Currency.php`, a class that extends `Field`. `--extends` names a built-in type to extend instead: `field`, `text`, `textarea`, `number`, `boolean`, `date`, `datetime`, `code`, `badge`, `status`, `file` or `image`. `--custom` also writes a React component and its import. See [Frontend](frontend.md).

## Pages

```bash
php artisan lathe:page Reports
```

This writes the page class and `resources/js/lathe/pages/Reports.tsx`. See [Pages](pages.md).

## Plugins

```bash
php artisan lathe:plugin acme/price-tracker
php artisan lathe:plugin Insights --inline
php artisan lathe:resource-plugin StripeInspector
```

The first writes a Composer package to `lathe-components/PriceTracker/`. The second writes an in-app plugin and its component. The third writes a resource plugin. `--skip-composer` stops `lathe:plugin` from offering to run `composer update`. See [Plugins](plugins.md).

## The frontend setup

The first command that writes a React component also sets up the extension build. That means `lathe:field --custom`, `lathe:filter --custom`, `lathe:widget --type=custom`, `lathe:page`, `lathe:plugin --inline` and `lathe:resource-plugin` without `--plugin`. It writes `resources/js/lathe/boot.ts`, the folders next to it and a `vite.lathe.config.ts`, and adds the three `@lathephp` packages to your `package.json`. It skips this when `boot.ts` exists. It never edits your `vite.config.ts`. [Frontend](frontend.md) explains the build.

## Publish the stubs

```bash
php artisan lathe:stubs
```

This copies the stubs to `stubs/lathe/` in your app. A generator uses your copy when one exists and the package's stub otherwise. `lathe:stubs` leaves files that already exist alone. Pass `--force` to overwrite them.

To change what `lathe:resource` writes, edit `stubs/lathe/resource.stub`. Every later `lathe:resource` uses it. Delete a file from `stubs/lathe/` to go back to the default.

### The stubs

| Stub | Used by |
|---|---|
| `resource.stub` | `lathe:resource` |
| `policy.stub` | `lathe:resource`, for the policy |
| `action.stub` | `lathe:action` |
| `lens.stub` | `lathe:lens` |
| `dashboard.stub` | `lathe:dashboard` |
| `filter-select.stub`, `filter-boolean.stub`, `filter-date.stub`, `filter-range.stub` | `lathe:filter --type=...` |
| `filter-custom.stub`, `filter-react.stub` | `lathe:filter --custom`: the class and the component |
| `widget-value.stub`, `widget-trend.stub`, `widget-partition.stub`, `widget-progress.stub`, `widget-table.stub`, `widget-bar.stub`, `widget-pie.stub`, `widget-radar.stub`, `widget-radial.stub`, `widget-help.stub` | `lathe:widget --type=...` |
| `widget-custom.stub`, `widget-react.stub` | `lathe:widget --type=custom`: the class and the component |
| `field.stub`, `field-react.stub` | `lathe:field`: the class, and the component with `--custom` |
| `page.stub`, `page-react.stub` | `lathe:page`: the class and the component |
| `resource-plugin.stub`, `resource-plugin-react.stub` | `lathe:resource-plugin`: the class and the component |
| `plugin.stub`, `plugin-react.stub` | `lathe:plugin --inline`: the class and the component |
| `boot.stub`, `vite.lathe.config.stub` | The frontend setup |

`lathe:install` writes `LatheServiceProvider.stub`, `user-resource.stub` and `main-dashboard.stub` from the package, and `lathe:plugin` copies `lathe-components-readme.md` from the package. Copies in `stubs/lathe/` do not change them. The package scaffold of `lathe:plugin vendor/package` is a whole folder of templates, `stubs/plugin-skeleton/`. `lathe:stubs` does not publish it.

### Placeholders

A stub holds `{{ name }}` style placeholders that the command fills in:

| Placeholder | Replaced with | Example |
|---|---|---|
| `{{ namespace }}` | The namespace of the class | `App\Lathe\Resources` |
| `{{ class }}` | The class name in StudlyCase | `UserPost` |
| `{{ name }}` | The class name as a headline | `User Post` |
| `{{ component }}` | The kebab-case component key (the filter key has `-filter` added) | `user-post` |
| `{{ modelNamespace }}` | The model class of a Resource | `App\Models\UserPost` |

`policy.stub` also gets `{{ model }}`, the model class. `field.stub` gets `{{ baseImport }}` and `{{ baseClass }}`, the field type to extend.

## What you see

After `php artisan lathe:resource Post`, the file `app/Lathe/Resources/Post.php` exists and the panel lists Posts in the sidebar. After `php artisan lathe:stubs`, the `stubs/lathe/` folder holds one file for each stub, and the next generator you run uses the copy you edit.
