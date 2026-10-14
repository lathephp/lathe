---
name: lathe-development
description: Build admin panels with Lathe, an admin panel builder for Laravel on Inertia, React and shadcn/ui. Use when working under app/Lathe/, extending a Lathe Resource, Action, Filter, Lens, Dashboard, Widget or Page class, running lathe:* artisan commands (lathe:resource, lathe:action, lathe:filter, lathe:lens, lathe:widget, lathe:dashboard, lathe:field, lathe:page, lathe:plugin), writing tests with Lathe\Testing\LatheTest, or when the user mentions a Lathe resource, field, lens, widget or panel.
---

# Lathe Development

Lathe is an admin panel builder for Laravel, in the **Laravel Nova tradition**: each Eloquent model gets a **Resource** class whose `fields()` declare the index columns, the detail rows and the form inputs, and **Actions**, **Filters**, **Lenses**, **Dashboards** and **Widgets** hang off it. The Nova *concepts* carry over, the *APIs differ*. Follow this skill, not Nova habits. "Differences from Nova" lists the traps. The panel lives at `/lathe`.

Everything Lathe ships is under the `Lathe\` namespace, and the request type is `Lathe\Http\Requests\LatheRequest`. **Never `use Laravel\Nova\...`.** The browser side is Inertia, React and shadcn/ui, and it is written in the `@lathephp/core` and `@lathephp/ui` packages.

## Where code lives: `app/Lathe/`

```text
app/Lathe/
  Resources/    auto-discovered, no registration needed
  Actions/      returned from a resource's actions()
  Filters/      returned from filters()
  Lenses/       returned from lenses()
  Dashboards/   registered in app/Providers/LatheServiceProvider.php
  Widgets/      returned from a dashboard's, a resource's or a lens's widgets()
  Fields/       custom field classes, paired with a React component
  Pages/        standalone pages, paired with a React component, registered by hand
  Plugins/      in-app plugins (lathe:plugin --inline)
resources/js/lathe/   React components of custom fields, filters, widgets and pages
lathe-components/     plugin packages that live in the project
```

Only **Resources** are auto-discovered. Everything else is reached by reference (listed on a resource) or by registration in `app/Providers/LatheServiceProvider.php`. That provider extends `Lathe\LatheApplicationServiceProvider` and overrides these hooks: `gate()`, `authorization()`, `registerResources()`, `registerDashboards()` and `registerPlugins()`. Pages have no hook. Call `Lathe::registerPages(...)` from `boot()`, after `parent::boot()`.

## Workflow

1. **Scaffold with the artisan generator and never write the file by hand.** `php artisan lathe:resource Post --no-interaction`. The generators are `lathe:resource`, `lathe:action`, `lathe:filter`, `lathe:lens`, `lathe:dashboard`, `lathe:widget`, `lathe:field`, `lathe:page`, `lathe:plugin` and `lathe:resource-plugin`. Run `php artisan lathe:install` once in a fresh app, and `php artisan lathe:user` to create a user who can sign in. Done when the file exists under the right `app/Lathe/` folder and extends the right base class.
2. **Configure it** with the sections below. Done when a resource's `configure()` calls `->model(...)` and `fields()` returns fields, and a satellite implements its one required method (`handle`, `apply`, `query`, `widgets` or `calculate`).
3. **Wire it.** Resources: nothing, they are discovered. Actions, filters, lenses: add an **instance** (`new Foo` or `Foo::make()`) to the resource's `actions()`, `filters()` or `lenses()`. Dashboards, pages and plugins: register them in `LatheServiceProvider`. Done when the class is reachable from a resource or the provider.
4. **Test it** with the kit (`Lathe\Testing\LatheTest`), see [reference.md](reference.md) for the recipe. Run `php artisan test --compact --filter=...`. Done when a test exercises the new behaviour and passes.

When the setup looks wrong, run `php artisan lathe:doctor`. It checks the Lathe setup and says what is missing.

**When unsure of a method, option, default or argument type, read the source. Do not guess.** Everything is in `vendor/lathe/lathe/src`: fields in `src/Field/`, resources in `src/Resources/`, `src/Actions/`, `src/Filters/`, `src/Lenses/`, `src/Widgets/`, `src/Pages/`, and the test kit in `src/Testing/`. `php artisan lathe:stubs` publishes the generator templates to `stubs/lathe` for editing. Lathe is not in Boost's `search-docs`, so the package source is the source of truth.

## Resource anatomy

```php
<?php

namespace App\Lathe\Resources;

use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Resources\Resource;
use Lathe\Resources\ResourceDefinition;

class Post extends Resource
{
    protected function configure(ResourceDefinition $definition): ResourceDefinition
    {
        return $definition
            ->model(\App\Models\Post::class)   // the Eloquent MODEL class
            ->title('title')                   // a column, or fn ($model) => string
            ->subtitle('slug')
            ->searchable(['id', 'title'])      // the columns the search box reads
            ->with(['author'])                 // eager-load what the fields read
            ->icon('FileText')                 // a Lucide icon component name
            ->group('Content');
    }

    public function fields(LatheRequest $request): array
    {
        return [
            Field::id()->sortable(),
            Field::text('Title')->sortable()->required(),
            Field::belongsTo('Author'),
        ];
    }

    public function actions(LatheRequest $request): array
    {
        return [];
    }
}
```

`configure(ResourceDefinition)` is `protected` and is the place to set behaviour. Every setting can also be a PHP attribute on the class (`Lathe\Attributes\Resource\Group`, `Icon`, `NavigationLabel`, `TableStyle` and others). The attribute sets a constant, and a call in `configure()` replaces it. Pick one style per resource, and use `configure()` when you need a closure.

Chainable builder methods (read `src/Resources/ResourceDefinition.php` for argument types): `model`, `title`, `subtitle`, `with`, `searchable`, `globallySearchable`, `perPage`, `paginationType`, `clickAction`, `polling`, `softDeletes`, `savedTabs`, `trafficCop`, `icon`, `group`, `navigationLabel`, `displayInNavigation`, `canonical`, `parent`, `canSee`, `allowAll`, `countStrategy`, `bulkInlineMax`, `pickerResults`, `defaultGroup`, `tableStyle`, `borderless`.

Hooks to override on the resource:

- `fields(LatheRequest)` is **required**. `actions()`, `filters()`, `lenses()`, `widgets()` default to `[]`.
- **Per-context fields:** `fieldsForIndex`, `fieldsForDetail`, `fieldsForCreate`, `fieldsForUpdate`, `fieldsForPreview` and `fieldsForPeeking` return fields for one place and replace `fields()` there.
- **Query hooks** are static methods that take the request and a builder and return the builder: `indexQuery`, `detailQuery`, `editQuery`, `searchQuery`, `widgetQuery`, `relatableQuery`, `replicateQuery`. `indexQuery()` is also where a default sort goes. `detailQuery`, `editQuery`, `relatableQuery` and `replicateQuery` do **not** fall back to `indexQuery`, so a scope must be repeated on each.
- **Lifecycle hooks** are static: `beforeCreate`/`afterCreate`, `beforeUpdate`/`afterUpdate`, `beforeDelete`/`afterDelete`, plus the restore and force-delete pairs. Each gets the request and the model.
- `defaultAttributes(LatheRequest)` gives a new model its starting attributes. `replicateExcept()` lists what a copy leaves empty.

The generated policy is the next step: `lathe:resource` writes `app/Policies/PostPolicy.php` when the model has none.

## Fields

Every field comes from a `Field::*` **static factory**: the label first, the attribute second. When the attribute is left out, Lathe uses the snake case of the label, so `Field::text('Reorder level')` reads `reorder_level`. There is no `Text::make()`, and a field is frozen once `fields()` returns it.

- **Text and content:** `text`, `textarea`, `slug`, `email`, `url`, `password`, `passwordConfirmation`, `code`, `markdown`, `tiptap`, `color`, `token`, `heading`, `line`, `stack`, `keyValue`, `repeater`
- **Numbers, dates, money:** `id`, `number`, `currency`, `date`, `dateTime`, `timezone`, `country`, `sparkline`
- **Choice and state:** `boolean`, `booleanGroup`, `select`, `multiSelect`, `status`, `badge`, `icon`, `hidden`
- **Media:** `image`, `file`, `audio`, `avatar`, `gravatar`, `uiAvatar`
- **Relationships:** `belongsTo`, `hasOne`, `hasMany`, `belongsToMany`, `hasOneThrough`, `hasManyThrough`, `hasOneOfMany`, `morphTo`, `morphOne`, `morphMany`, `morphToMany`, `morphedByMany`, `tag`
- **Plain:** `Field::make('Name')` builds a plain field, and its optional third argument formats the value.

**Rules that prevent mistakes:**

- **`rules()` is variadic, not an array:** `->rules('required', 'max:255')`. `required()` and `nullable()` add the rule and mark the field. `creationRules()` and `updateRules()` split by form.
- **Options fields need their options, or they render empty.** `Field::select('Status')->options(['draft', 'published'])` takes a list, a map of value to label, a closure or a backed enum class. `Field::badge('Stage')->map(['won' => 'success'])`, `Field::status('Import')->loadingWhen(['running'])->failedWhen(['failed'])`.
- **A computed field** takes a closure as its attribute and is read-only: `Field::text('Full name', fn ($user) => $user->first.' '.$user->last)`. It cannot be sorted and has no input.
- **A relation field takes a Resource class, not a model:** `Field::hasMany('Comments', 'comments', Comment::class)`. The arguments are the label, the **relationship method name** (never the foreign key) and the related **Resource**. Both are optional and Lathe guesses them from the label, so give them when the guess is wrong or call `->resource(User::class)`.
- **Where a field shows** has a show/hide pair for six places (index, detail, create, update, preview, peek): `hideFromIndex`, `onlyOnForms`, `exceptOnForms`, `onlyOnDetail`, `hideWhenCreating`, `showWhenPeeking` and so on. `canSee()` and `canSeeWhen()` hide a field per user.
- **Dependent fields** use conditions, which run in the browser and are checked again on the server: `->visibleWhen(Condition::field('type')->eq('company'))`, `requiredWhen()`, `readonlyWhen()`, `optionsFrom()`. For server logic use `dependsOn(['quantity', 'price'], fn (FieldPatch $field, LatheRequest $request, FormData $form) => ...)`.
- **`filterable()`, `inline()` and `summarize()` exist on specific field types only.** Read the field class before you call one. `inline()` throws on a type that does not support it.
- **Closures get their parameters by name, then by type.** Name only the ones you need: `fn ($record, LatheRequest $request) => ...`.

See [reference.md](reference.md) for the full modifier tables and per-type options.

## Layout: sections, tabs, grids

Wrap fields in layout containers (`Lathe\Layout\Section`, `Tab`, `TabGroup`, `Grid`) inside `fields()`. They shape the detail page and the forms. Fields outside a container land in a default "Details" section. A field attribute may appear **once** per page, or Lathe stops with an error.

```php
public function fields(LatheRequest $request): array
{
    return [
        Section::make('Pricing', [
            Field::currency('Price', 'price_cents')->asMinorUnits(),
            Field::number('Stock'),
        ])->collapsible()->columns(2),

        Tab::group('Product', [
            Tab::make('Details', [Field::text('Name')]),
            Tab::make('Media', [Field::image('Cover')]),
        ]),
    ];
}
```

Give a tab group two or more tabs. Layout containers are **flattened to columns on the index**, so hide the fields that should not be columns with `hideFromIndex()`.

## Actions, filters, lenses

```php
<?php

namespace App\Lathe\Actions;

use Illuminate\Support\Collection;
use Lathe\Actions\Action;
use Lathe\Actions\ActionFields;
use Lathe\Actions\ActionResult;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;

class PublishPost extends Action
{
    public function fields(LatheRequest $request): array
    {
        return [Field::date('Publish at')->required()];
    }

    public function handle(ActionFields $fields, Collection $models): ActionResult
    {
        foreach ($models as $post) {
            $post->publishAt($fields->publish_at);
        }

        return ActionResult::message('Published.');
    }
}
```

```php
<?php

namespace App\Lathe\Filters;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Filters\Filter;
use Lathe\Http\Requests\LatheRequest;

class Published extends Filter
{
    public function apply(LatheRequest $request, Builder $query, mixed $value): Builder
    {
        return $query->where($query->qualifyColumn('published'), $value);
    }

    public function options(LatheRequest $request): array
    {
        return ['Yes' => 1, 'No' => 0]; // label => value
    }
}
```

```php
<?php

namespace App\Lathe\Lenses;

use Illuminate\Database\Eloquent\Builder;
use Lathe\Field\Field;
use Lathe\Http\Requests\LatheRequest;
use Lathe\Lenses\Lens;

class TopAuthors extends Lens
{
    public function fields(LatheRequest $request): array
    {
        return [Field::id(), Field::text('Name')->sortable(), Field::make('Posts', 'posts_count')->sortable()];
    }

    public static function query(LatheRequest $request, Builder $query): Builder
    {
        // Keep the model key in the select. Lathe needs it to pick rows and run actions.
        return static::withOrdering($request, static::withFilters($request, $query->withCount('posts')));
    }
}
```

Must-know:

- **Wire** each by adding an instance to the resource's `actions()`, `filters()` or `lenses()`.
- **An action returns an `ActionResult`**, and `handle()` runs once per selected row, each in its own transaction (a queued run hands a whole chunk to `handle()`). Factories: `message`, `danger`, `redirect`, `openInNewTab`, `visit`, `download`, `modal`, `emit`, `deleted`. Confirm with `(new PublishPost)->confirmable('Publish :count posts?')`, or extend `Lathe\Actions\DestructiveAction`. An action needs a selection unless it is `standalone()`, and `sole()` needs exactly one row.
- **Filters:** pick the base class. `Filter` plus `options()` is a select (`label => value`), `BooleanFilter`, `DateFilter` (`from` and `to`) and `RangeFilter` (`min` and `max`) shape `$value` for you. Before you write one, check whether `->filterable()` on a field already does the job. Use `qualifyColumn()` so a joined query still works.
- **Lenses** read through the resource's `indexQuery()`, so they never show a row the index hides. A lens has its own `filters()` but **no `actions()`**: its rows run the resource's actions. Call `withFilters()` and `withOrdering()` inside `query()` when it wraps a subquery.
- Queue an action with `ShouldQueue`. A run over more than 500 rows (`limits.bulk_inline_max`) is queued anyway and needs a worker.

## Authorization

Lathe uses **standard Laravel policies**, found from the resource's model, and **denies by default**:

- A resource with no policy denies every ability.
- A policy without the method for an ability denies it. That includes `viewAny`, so a policy without it hides the resource from the sidebar.
- `lathe:resource` writes a policy that allows `viewAny` and `view` only. Add `create`, `update`, `delete`, `restore`, `forceDelete` and the rest as you allow them.
- An action asks the policy for `runAction`, a destructive one also for `runDestructiveAction`. There is no fallback to `update` or `delete`.
- A widget on a resource needs `viewWidget($user, string $widgetKey)` on the policy.

Override `public static ?string $policy` to point at another policy, `deniedAbilities()` to deny an ability whatever the policy says, and `allowAll()` in `configure()` to skip policy checks (it logs a warning, use it for a demo only). **Do not bypass policies. Row and action access depends on them.**

Second axis: `canSee(fn (LatheRequest $request) => bool)` and `canSeeWhen($ability)` hide a resource, field, action, widget, dashboard or page from a user.

Panel access: a `viewLathe` gate in `LatheServiceProvider::gate()` admits a signed-in user on the panel guard, **in every environment, `local` included**. The email list starts empty, so nobody gets in until you add an email. `Lathe::auth()` replaces the gate with a callback. In tests, define the gate yourself, or every request is a 403 (see [reference.md](reference.md)).

## Audit log and notifications

- **Action events:** add the `Lathe\ActionEvents\Actionable` trait to a model and Lathe records its creates, updates, deletes and action runs, and shows them on the record's detail page. The log resource is read through `Lathe\ActionEvents\ActionEvent`, and its shipped policy denies everyone until the app registers one with `Gate::policy()`.
- **Notifications:** `$user->notify(LatheNotification::make()->success()->message('Done')->action('View', Lathe::path().'/resources/orders'))`. The user model needs Laravel's `Notifiable`. Action URLs must be absolute panel paths, so build them with `Lathe::path()`.

## Limits that shape the code

Lathe is built for tables of millions of rows, so it caps what a request does. Do not fight the caps, work with them.

- **Counts are capped.** The index counts at most 10,001 rows and shows "10,000+". `countStrategy()` takes a `Lathe\Query\CountStrategy` (`Capped`, `Exact`, `Estimated`, `None`). Use `Exact` only on a table that stays small.
- Pages are 25 rows by default, at most 100. There is no "show all".
- Search matches the start of a column (`term%`) unless a column opts in to "contains".
- A relation picker shows 25 results and searches on the server. A select with more than 1,000 options throws, use a relation field.
- Widget values are cached for 60 seconds and cleared when their table is written.

## Differences from Nova (do not fall into these)

- **Licence and stack:** Lathe is MIT, and the front end is Inertia, React and shadcn/ui, not Vue. A custom component is a React component.
- **Fields:** `Field::text('Name')`, not `Text::make('Name')`. One `Field` factory for everything. The namespace is `Lathe\...` and the request is `LatheRequest`.
- **Resource config** lives in `configure(ResourceDefinition)`, not in loose `public static $model` and `$title` properties.
- **No `cards()`:** use `widgets()`. **No `defaultOrderings()`:** use a static `indexQuery()`.
- **No Tools:** a standalone screen is a `Page` (`lathe:page`), and a packaged feature is a `Plugin` (`lathe:plugin`).
- **Action results** are `ActionResult`, not `Action::message()`.
- **Deny by default:** a missing policy or a missing policy method is a 403, where Nova lets it through.
- **Capped counts, a page size of at most 100, and queued bulk runs over 500 rows** are built in.
- **Satellites are instances:** `new Foo` or `Foo::make()`. Fields are static factories with no `::make()` after them.
- **Selects:** a filter's `options()` maps `label => value`, a field's `options()` maps `value => label`.

## Widgets, dashboards, custom components

- Widgets (value, trend, partition, bar, pie, progress, radar, radial, table, help, custom), dashboards, actions in depth and the testing recipe are in [reference.md](reference.md).
- **A custom field, filter, widget or page** is a PHP class, a React component, a `boot.ts` import and a **separate Vite build**. It is easy to get silently wrong without the playbook, so follow [custom-extensions.md](custom-extensions.md).
