# Building custom Lathe extensions (frontend-coupled)

A custom **field**, **filter**, **widget** or **page** has a PHP class and a React component, joined by a string **component key**. It also needs a Vite build that is separate from the app's own `vite.config.*`. Skip a step and the panel shows no error: the field falls back to a plain input, or the page is empty. Follow this playbook in order. For the PHP-only parts, see [SKILL.md](SKILL.md) and [reference.md](reference.md).

## Golden rules

1. **Generate, never hand-write.** `php artisan lathe:field StarRating --custom`, `lathe:filter PriceTier --custom`, `lathe:widget Clock --type=custom`, `lathe:page Reports`. The first React component also writes `resources/js/lathe/boot.ts`, the folders, `vite.lathe.config.ts`, and adds `@lathephp/core`, `@lathephp/ui` and `@lathephp/vite-plugin` to `devDependencies`. It then tells you to run your package manager's install.
2. **The component key must match on both sides.** The PHP class names it, the React file registers it with the same string. A mismatch fails silently.
3. **Every component file must be imported from `resources/js/lathe/boot.ts`.** A file that nothing imports never runs, so its key is never registered. The generators append the import for you. A file you add by hand needs one more line.
4. **Import React and the Lathe packages by bare name** (`react`, `react/jsx-runtime`, `react-dom`, `@lathephp/core`, `@lathephp/ui`). The panel's import map serves its own copy, so a plugin runs on the panel's React and API. A bundled second copy of React breaks hooks, and the build fails with "bundles react code" to stop you.
5. **Build after every change.** The panel loads the built module from `public/vendor/lathe/extensions`, not your source. In dev, run the Vite dev server so it loads the source and reloads.
6. **Use `@lathephp/ui` for UI.** It holds the shadcn/ui components the panel itself uses (`Card`, `Button`, `Input`, `Select`, `Dialog`, `Tabs`, `Table` and more), so a custom component looks like the rest of the panel and follows the theme.

## Lifecycle playbook (per type)

### Field

1. `php artisan lathe:field StarRating --custom` writes `app/Lathe/Fields/StarRating.php` and `resources/js/lathe/fields/StarRating.tsx`. `--extends` names a built-in type to extend (the default is `field`).
2. PHP: the class extends `Field` (or a typed field) and sets `protected string $component = 'star-rating';`. Use it like any field: `StarRating::make('Rating')->rules('nullable', 'integer', 'between:1,5')`.
3. React: call `field('star-rating', Component)` for a form input, or `field('star-rating', { form, index, detail })` to draw the index cell and the detail row too. Without `index` and `detail`, the panel shows the field's display text.
4. Build, then open the resource's create form.

### Filter

1. `php artisan lathe:filter PriceTier --custom` writes the class and `resources/js/lathe/filters/PriceTier.tsx`.
2. PHP: extend `Lathe\Filters\Filter`, implement `apply(LatheRequest, Builder, mixed $value)`, and return the key from `component()`.
3. React: `filter('price-tier', Component)`. The component gets `{ filter, value, onChange }`. Call `onChange(next)` with the value `apply()` should receive. The built-in keys are `select-filter`, `multi-select-filter`, `boolean-filter`, `range-filter` and `date-filter`.
4. List the filter in the resource's `filters()` as usual.

### Widget

1. `php artisan lathe:widget Clock --type=custom` writes a `CustomWidget` and the component under `resources/js/lathe/widgets/`.
2. PHP: extend `Lathe\Widgets\CustomWidget` and set `protected ?string $component`, or call `CustomWidget::make('Clock')->withComponent('app-clock')`. A custom card makes no value request. Hand data to the component with `withMeta([...])`. `height('dynamic')`, `dynamicHeight()` and `fixedHeight()` control its height.
3. React: `widget('app-clock', Component)`. The component gets `{ widget }`, and the data from `withMeta()` is on it.
4. Return the widget from `widgets()` on a dashboard, resource or lens.

### Page

1. `php artisan lathe:page Reports` writes `app/Lathe/Pages/Reports.php` and `resources/js/lathe/pages/Reports.tsx`. The page URL is `/lathe/pages/reports`.
2. PHP: extend `Lathe\Pages\Page`. The URI key is the kebab class name. `name()`, `icon()`, `canSee()` and `canSeeWhen('ability')` are available. A page has no auto-discovery: register it from `boot()` of `LatheServiceProvider`, after `parent::boot()`:

```php
public function boot(): void
{
    parent::boot();

    Lathe::registerPages(Reports::class);
}
```

3. React: `page('reports', Component)`. The key is the page's URI key. The component gets `{ pageKey, basePath, path }`.
4. Add a sidebar entry with `MenuItem::link('Reports', 'pages/reports')` in `Menu::mainMenu(...)`. A page appears nowhere on its own.

### Other extension points

- **Plugin:** `php artisan lathe:plugin acme/price-tracker` creates a Composer package under `lathe-components/`, with its own page and `package.json`. `--inline` creates an in-app plugin class and React page instead. List plugins with `php artisan lathe:plugins`. A plugin's React page registers with `plugin('uri-key', Component)` and shows at `/lathe/plugins/{uriKey}`. Register an in-app plugin from `registerPlugins()`: `Lathe::registerPlugins(HelloPlugin::make())`.
- **Resource plugin:** `php artisan lathe:resource-plugin ActivityTimeline` draws a panel on a resource's detail page. React: `resourcePlugin('activity-timeline', Component)` with `{ resourceName, resourceId }`.
- **Action modal:** an action returns `ActionResult::modal('confirm-refund', [...])`, and `actionModal('confirm-refund', Component)` draws it.
- **Render hooks:** `renderHook(position, Component, { resources: ['orders'] })` adds a component at a position of the panel.

## Build and wiring details

`vite.lathe.config.ts` at the project root is the whole config:

```ts
import { lathe } from '@lathephp/vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
  plugins: [lathe({ mode: 'app' })],
});
```

- The app build bundles `resources/js/lathe/boot.ts` (set `bootFile` to change it) and writes the module, a `manifest.json` and any CSS to `public/vendor/lathe/extensions/`. Commit or deploy that folder, and let the panel's page load it from there.
- **Build once:** `pnpm exec vite build --config vite.lathe.config.ts` (or the `npx` or `bunx` form of your package manager). **Develop:** `pnpm exec vite --config vite.lathe.config.ts`. The dev server writes a `hot` file next to the manifest, the panel loads the boot module from the dev server, and a stopped server removes the file.
- This build is separate from your app's `vite.config.*` and `npm run build`. Run both when a deploy needs both, and run the Lathe build in CI.
- A path-repository plugin that names `extra.lathe.entry` in its `composer.json` is built and rebuilt by the app build too (`autoDiscover`, on by default). A plugin built on its own uses `lathe({ mode: 'plugin', name: 'price-tracker', entry: 'resources/js/index.ts' })` and writes `dist/{name}.js`.
- `php artisan lathe:assets` copies the scripts and styles that plugins register with `Lathe::script()` and `Lathe::style()` into `public/`. Run it on deploy.
- After a change that does not show, run `php artisan lathe:doctor` and check the browser console for an unregistered component key.

## React prop contracts (from `@lathephp/core`)

```tsx
import { field, filter, page, widget } from '@lathephp/core';
import type { FieldProps, FilterProps, PageProps, WidgetProps } from '@lathephp/core';
```

| Registered with | Component receives |
|---|---|
| `field(key, Form)` | `FieldProps`: `field` (the schema: `label`, `placeholder`, `help`, `meta`...), `value`, `onChange(value)`, `id`, `describedBy`, `invalid`, `disabled`, optional `values` (the other form values) |
| `field(key, { index, detail })` | index gets `{ field, cell }`, with `cell.value` and `cell.display`. Detail gets `{ field }` with the record's value on it |
| `filter(key, Component)` | `FilterProps`: `filter` (`name`, `key`, `options`, `default`), `value`, `onChange(value)` |
| `widget(key, Component)` | `WidgetProps`: `widget` (`name` and the `withMeta()` data) and `value` |
| `page(key, Component)` | `PageProps`: `pageKey`, `basePath`, `path` |
| `plugin(uriKey, Component)` | `PluginPageProps` |
| `resourcePlugin(key, Component)` | `ResourcePluginProps`: `resourceName`, `resourceId` |
| `actionModal(name, Component)` | `ActionModalProps` |

On a form input, put `id` on the control, `aria-describedby={describedBy}` and `aria-invalid={invalid || undefined}` on it, and honour `disabled`. A label already points at `id`.

Helpers from the same package: `useQuery` and `queryKeys` (data fetching), `request` (the panel's HTTP client with the CSRF token), `toast`, `visit` and `router` (navigation), `useTranslation` (`__('text')`), `useTheme`, `on`, `emit` (events), `formatCurrency`, `formatDate`, `formatNumber`, and the panel's own data hooks (`useResourceIndex`, `useWidgetValue`).

## Data round trip

- **Form value out:** `onChange(value)` stores the value in the form. On submit Lathe sends it under the field's attribute, runs the field's rules, and fills the model with `fillUsing()` or the plain write. A JSON column needs an `array` or `json` cast on the model.
- **Value in:** the panel sends the value the field resolves (`resolveUsing()`, else the attribute). `displayUsing()` changes the text on the index and detail, never the form value.
- **Validation:** the PHP rules decide. A failure returns 422 and the form sets `invalid` and shows the message. Never trust the component to check a value.
- **A custom filter** reads and writes one value. Whatever `onChange` passes is what `apply()` gets as `$value`, after Lathe checks it, so return the shape `apply()` expects.
- **A custom widget** has no server value. Fetch what it needs with `request()` or `useQuery()` from an endpoint you own, or pass data through `withMeta()`.
- **Render data as text.** Do not use `dangerouslySetInnerHTML` on a value from the record or the server.

## Canonical skeletons

```tsx
// resources/js/lathe/fields/StarRating.tsx
import { field } from '@lathephp/core';
import type { FieldProps } from '@lathephp/core';

function StarRating({ field: definition, value, onChange, id, describedBy, invalid, disabled }: FieldProps) {
  const current = typeof value === 'number' ? value : 0;

  return (
    <div id={id} role="group" aria-label={definition.label} aria-describedby={describedBy} aria-invalid={invalid || undefined}>
      {[1, 2, 3, 4, 5].map((star) => (
        <button key={star} type="button" disabled={disabled} aria-pressed={star === current} onClick={() => { onChange(star); }}>
          {star <= current ? '★' : '☆'}
        </button>
      ))}
    </div>
  );
}

field('star-rating', StarRating);
```

```tsx
// resources/js/lathe/widgets/Clock.tsx
import { widget } from '@lathephp/core';
import { Card } from '@lathephp/ui';

function Clock({ widget: card }: { widget: { name: string } }) {
  return (
    <Card className="p-6">
      <h3 className="font-semibold leading-none tracking-tight">{card.name}</h3>
      <p>{new Date().toLocaleTimeString()}</p>
    </Card>
  );
}

widget('app-clock', Clock);
```

```tsx
// resources/js/lathe/boot.ts
import './fields/StarRating';
import './widgets/Clock';
```

## PHP side

```php
<?php

namespace App\Lathe\Fields;

use Lathe\Field\Field;

class StarRating extends Field
{
    protected string $component = 'star-rating';
}
```

```php
<?php

namespace App\Lathe\Widgets;

use Lathe\Widgets\CustomWidget;

class Clock extends CustomWidget
{
    protected ?string $component = 'app-clock';
}
```

Extend a typed field (`NumericField`, `TextField`, `BooleanField` or another class in `src/Field/`) when you want its rules and casting, and keep only the component of your own. Read the parent before you do, because it may set rules the form relies on.

Test the PHP side with the kit in [reference.md](reference.md): the resource's `creationFields()` response lists the field with your `component` key. The React side builds with the Vite command above.
