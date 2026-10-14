---
title: Extension API
description: The browser API of @lathephp/core and @lathephp/ui that fields, filters, widgets, pages and plugins build on.
order: 6
---

# Extension API

Every extension of the panel runs in the browser and talks to the panel through two packages. `@lathephp/core` is the API: it registers your components and gives them requests, navigation, toasts, shortcuts, hooks and types. `@lathephp/ui` is the UI kit that the panel itself uses.

```ts
import { field, plugin, request, toast } from '@lathephp/core';
import { Button, Card } from '@lathephp/ui';
```

Both are plain ES modules. The panel's import map points them at the panel's own files, so there is no global object and no second copy of React. In an app, `@lathephp/vite-plugin` builds your code against them. In a distributed plugin, the build leaves them out and the panel supplies them. See [Frontend](frontend.md) and [Plugin distribution](plugin-distribution.md).

`apiVersion` exports the semantic version of the API. Everything the packages export is public API and follows semver. Anything not exported is internal.

## Register components

Each call binds a key to a React component. The key must match the key the PHP class sends:

| Call | Registers | The key matches |
|---|---|---|
| `field(key, renderers)` | A custom field | The field's `$component` |
| `filter(key, component)` | A custom filter | The filter's `component()` |
| `widget(key, component)` | A custom widget | The widget's component key |
| `plugin(uriKey, component)` | A plugin page at `/lathe/plugins/{uriKey}` | The plugin's `uriKey()` |
| `page(uriKey, component)` | A page at `/lathe/pages/{uriKey}` | The page's `uriKey()` |
| `resourcePlugin(key, component)` | A block on a detail page | The resource plugin's `component()` |
| `actionModal(name, component)` | The modal of an action result | The name given to `ActionResult::modal()` |

A key that exists is replaced: the last registration wins. A built-in field keeps its own renderers for its key.

A field takes an object with the renderers it needs: `form`, `index` and `detail`. A bare component is the form input:

```ts
import { field, filter, plugin, resourcePlugin } from '@lathephp/core';

field('star-rating', { form: StarRatingForm, index: StarRatingIndex, detail: StarRatingDetail });
filter('price-tier-filter', PriceTier);
plugin('insights', Insights);
resourcePlugin('activity-timeline', ActivityTimeline);
```

Every call also takes a lazy component, so the code loads when it is first drawn:

```ts
import { lazy } from 'react';

page('reports', lazy(() => import('./pages/Reports')));
```

The props each component receives:

| Registered with | Props type | What it carries |
|---|---|---|
| `field` form | `FieldProps` | `field`, `value`, `onChange`, `id`, `describedBy`, `invalid`, `disabled`, `values` |
| `field` index | `IndexFieldProps` | `field`, `cell` (`value`, `display`) |
| `field` detail | `DetailFieldProps` | `field`, with the record's `value` and `display` on it |
| `filter` | `FilterProps` | `filter`, `value`, `onChange` |
| `widget` | `WidgetProps` | `widget`, `value` |
| `plugin` | `PluginPageProps` | `pluginKey`, `basePath`, `path` |
| `page` | `PageProps` | `pageKey`, `basePath`, `path` |
| `resourcePlugin` | `ResourcePluginProps` | `resourceName`, `resourceId` |
| `actionModal` | `ActionModalProps` | `data`, `close` |

`path` is the part of the URL after the plugin or page key. It is an empty string when there is none.

## Render hooks

A render hook adds a component at a fixed position of the panel, without replacing anything. Every hook for a position renders, in the order they were added:

```ts
import { renderHook } from '@lathephp/core';

renderHook('detail:header-actions', SyncButton, { resources: ['users'] });
```

There are ten positions:

| Position | Where it renders |
|---|---|
| `head` | The document head |
| `header:end` | The end of the top bar |
| `sidebar:before` | Above the sidebar navigation |
| `sidebar:after` | Below the sidebar navigation |
| `page:before` | Above the page content |
| `page:after` | Below the page content |
| `footer:before` | Above the footer |
| `index:header-actions` | The action area of a Resource index header |
| `detail:header-actions` | The action area of a Resource detail header |
| `auth:after-form` | Beneath the sign-in form |

Pass `{ resources: [...] }` with Resource uriKeys to limit a hook to those Resources. A hook with no `resources` option renders everywhere. A hook that is limited does not render on a position that has no Resource.

The component receives `RenderHookProps`: `resource` (the uriKey) on Resource pages and `record` on detail pages.

```tsx
import { toast } from '@lathephp/core';
import type { RenderHookProps } from '@lathephp/core';
import { Button } from '@lathephp/ui';

export function SyncButton({ resource }: RenderHookProps) {
  return (
    <Button size="sm" variant="outline" onClick={() => toast.info(`Syncing ${resource ?? ''}`)}>
      Sync
    </Button>
  );
}
```

Each hook sits inside an error boundary. A hook that throws renders nothing and logs the error, and the rest of the page works.

## Replace a part of the panel

`registry.replace(key, component)` swaps a built-in part for your component:

```tsx
import { registry } from '@lathephp/core';
import type { SlotProps } from '@lathephp/core';

function Footer({ slotVersion }: SlotProps) {
  return <footer>Version {slotVersion}</footer>;
}

registry.replace('shell.footer', Footer);
```

The keys are `shell.header`, `shell.sidebar`, `shell.footer`, `auth.login`, and a kind plus a component key written as `kind:key`. The kinds are `field.index`, `field.detail`, `field.form`, `filter`, `widget` and `action-modal`, for example `field.index:badge-field`. The replacement gets the props of the part it replaces, plus `slotVersion`, a number that changes only in a major release. An unknown key throws in development and logs a warning in production.

## Make requests

`request()` is the panel's HTTP client. It sends the CSRF token, asks for JSON, answers `null` for a `204`, and throws an `ApiError` for a failed response:

```ts
import { request } from '@lathephp/core';

const summary = await request<Summary>('/lathe/api/plugins/insights/summary');
```

Its second argument takes `method`, `body` (sent as JSON), `headers`, `signal`, `responseType` (`'blob'` for a download) and `toastOnError`. Build API addresses from `useLathe().apiPath`, so a changed panel path does not break them.

```ts
await request(`${apiPath}/plugins/insights/refresh`, { method: 'POST', body: { force: true } });
```

`csrfHeaders()` returns the headers if you need to call `fetch` yourself.

## Navigate

`visit(path)` goes to a panel path through Inertia and adds the panel prefix. `url(path)` builds the address without going there:

```ts
import { url, visit } from '@lathephp/core';

visit('resources/users');   // /lathe/resources/users
const href = url('pages/reports');
```

`router` is the panel's Inertia router with `visit(url, options)` and `reload(options)` for the cases that need Inertia's options. It exists once the panel has started, so call it from a component or from a `booting()` callback.

## Read data from PHP

Values from `Lathe::provideToScript()` are read with `config(key)`:

```ts
import { config } from '@lathephp/core';

const viewer = config('insightsViewer') as string;
```

`useLathe()` gives a component the panel's `basePath`, its `apiPath`, `config` and the signed-in `auth`:

```tsx
const { apiPath, auth } = useLathe();
```

## Show toasts

```ts
import { toast } from '@lathephp/core';

toast.success('Summary refreshed.');
toast.error('Could not load the summary.');
toast.info('Syncing in the background.');
toast.warning('Some records were skipped.');
```

Each takes a message and an optional second argument with `duration` in milliseconds (4,000 by default), an `action` (`{ label, onClick }`, such as Retry) and `links` (`{ label, href, newTab }`). At most three toasts show at once.

## The event bus

Components talk to each other over a global bus. `on` returns the function that removes the listener:

```tsx
import { emit, on, toast } from '@lathephp/core';
import { useEffect } from 'react';

useEffect(() => on('gallery:ping', () => toast.info('Ping received')), []);

emit('gallery:ping');
```

`once(event, listener)` removes itself after one call, and `off(event, listener)` removes a listener you hold. The panel itself emits `actions:ran` after an action runs, with `{ action }`, and `filters:changed` when the index filters change, with `{ resource, filters }`.

## Keyboard shortcuts

`addShortcut(keys, handler)` registers a shortcut that works anywhere in the panel and returns the function that removes it. Write a single key, a combination such as `mod+k`, or a sequence such as `g u` ("press `g`, then `u`" within one second):

```tsx
import { addShortcut, useShortcut, visit } from '@lathephp/core';

const remove = addShortcut('g u', () => visit('pages/ui-gallery'));

function Search() {
  useShortcut('mod+k', () => openPalette());
  return null;
}
```

A single key does not fire while the user types in a field. `disableShortcut(keys)` turns one off, a built-in included, until `enableShortcut(keys)`. `pauseShortcuts()` stops all of them and `resumeShortcuts()` undoes one pause, so pauses add up. `usePauseShortcuts(active)` pauses while a component is mounted, as a modal does. `shortcutsEnabled()` tells whether the app turned shortcuts off with `keyboard_shortcuts.enabled`.

## Hooks

| Hook | Returns |
|---|---|
| `useLathe()` | `basePath`, `apiPath`, `config`, `auth` |
| `useTranslation()` | `{ __ }`, the translator of the user's locale |
| `useTheme()` | `appearance` (`'light'`, `'dark'` or `'system'`) and `setAppearance` |
| `useResourceIndex(resource, params?)` | A page of a Resource's index |
| `useResourceDetail(resource, id)` | One record and its detail fields |
| `useResourceActions(resource, params?)` | The actions of a Resource or a record |
| `useExecuteAction(resource)` | `execute`, `data`, `error`, `isPending` for running an action |
| `useInlineField(resource, id, field)` | `save`, `pending`, `error`, `isSaving` for saving one field of a record |
| `useWidgetList(source)` and `useWidgetValue(source, widget)` | The cards of a dashboard or Resource, and one card's value |
| `useShortcut(keys, handler, active?)` and `usePauseShortcuts(active?)` | Nothing. They bind to the component's life |
| `useNavigationProgress()` | `true` while the panel loads the next page |
| `useDebounce(value, delay)` | The value, once it stops changing |
| `useQuery`, `useQueryClient`, `queryClient`, `queryKeys` | The TanStack Query client the panel shares |

The data hooks return `{ data, error, isLoading, isFetching, refetch }`. They share the panel's query client, so a record you read is cached with the panel's own reads, and an action refreshes it. The translator fills `:name`, `:Name` and `:NAME` placeholders:

```tsx
const { __ } = useTranslation();

__('Order #:id is on hold.', { id: 12 });
```

Format values for the user's locale with `formatNumber`, `formatCurrency`, `formatDate` and `formatDateTime`.

## Components

The panel's own building blocks are exported, so a custom page looks like the rest of the panel: `AppLayout`, `ResourceTable`, `FilterMenu`, `ActionDropdown`, `DetailFieldPanel`, `DetailHeader`, `WidgetGrid`, `WidgetCard` and `Excerpt`.

## UI kit

Import components from `@lathephp/ui`, never from Base UI. The main entry holds the everyday set: Accordion, Alert, AlertDialog, Avatar, Badge, Button, Card, Checkbox, Dialog, DropdownMenu, Input, Kbd, Label, Popover, Progress, RadioGroup, Select, Separator, Sheet, Skeleton, Slider, Spinner, Switch, Table, Tabs, Textarea and Tooltip, with their parts. Heavier components load from a subpath, for example `@lathephp/ui/calendar`. See [Plugin distribution](plugin-distribution.md#the-npm-packages) for the list.

## Run code before the first render

The panel loads every plugin module before it first renders. A registration you make while the module loads is ready in time. For explicit ordering, put it in `booting()`:

```ts
import { booting, field } from '@lathephp/core';

booting(() => {
  field('rating-field', { form: RatingForm });
});
```

A callback registered after the panel booted runs at once.

## Types

`@lathephp/core` exports the types you need as you write components: `FieldProps`, `IndexFieldProps`, `DetailFieldProps`, `FilterProps`, `WidgetProps`, `PluginPageProps`, `PageProps`, `ResourcePluginProps`, `ActionModalProps`, `RenderHookProps`, `RenderHookName`, `SlotProps`, `FieldData`, `FilterData`, `WidgetData`, `ActionData`, `ResourceRow`, `ResourceMeta`, `LayoutBlock`, `PaginatedResponse`, `ResourceIndexResponse`, `ResourceDetailResponse`, `NotificationData`, `SharedData` and more. Run `pnpm exec tsc` in your project to check your code against them.

## Test your components

`@lathephp/core/testing` exports the same API without the guard that throws outside the panel, plus `mockLathe(data)` to stand in for the panel's shared data. Alias `@lathephp/core` to it in your Vitest config. [Plugin distribution](plugin-distribution.md#test-the-module) shows how.

## What you see

Components you register draw in the panel where their key is used: a field in the form, a page under the sidebar entry that links to it, a hook at its position. A toast from `toast.success()` appears in the corner for four seconds. A shortcut such as `g u` runs its handler when you press the keys in turn.
