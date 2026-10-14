import type { ComponentType, LazyExoticComponent } from 'react';
import type { DetailFieldProps, FormFieldProps, IndexFieldProps } from './types';

/** The props a plugin page receives. */
export interface PluginPageProps {
  pluginKey: string;
  basePath: string;
  path?: string;
}

/** A page component; pass React.lazy(() => import('./Page')) to load it on first use. */
export type ComponentOrLazy<P> = ComponentType<P> | LazyExoticComponent<ComponentType<P>>;

/** The props a modal an action opens with modal(component, data) receives. */
export interface ActionModalProps {
  data: Record<string, unknown>;
  close: () => void;
}

/** The props a page registered with page(uriKey, component) receives at /lathe/pages/{uriKey}. */
export interface PageProps {
  pageKey: string;
  basePath: string;
  path?: string;
}

/**
 * The props a custom form field receives, the same as a built-in input's: call onChange(value)
 * with the new value, and put id, describedBy, invalid and disabled on the input.
 */
export type FieldProps = FormFieldProps;

/**
 * The components that draw a field's component key: form gets FieldProps, index gets
 * { field, cell } and detail gets { field } with the record's value and display on it.
 */
export interface FieldRenderers {
  form?: ComponentOrLazy<FieldProps>;
  index?: ComponentOrLazy<IndexFieldProps>;
  detail?: ComponentOrLazy<DetailFieldProps>;
}

/** The props a custom filter receives. */
export interface FilterProps {
  filter: { name: string };
  value: unknown;
  onChange: (value: unknown) => void;
}

/** The props a custom widget receives: its descriptor and the value the server computed. */
export interface WidgetProps {
  widget: { name: string };
  value: unknown;
}

/** The props a resource plugin on a detail page receives. */
export interface ResourcePluginProps {
  resourceName: string;
  resourceId: string;
}

/** Where a render hook shows in the panel. */
export type RenderHookName =
  | 'head'
  | 'header:end'
  | 'sidebar:before'
  | 'sidebar:after'
  | 'page:before'
  | 'page:after'
  | 'footer:before'
  | 'index:header-actions'
  | 'detail:header-actions'
  | 'auth:after-form';

/** The props a render hook receives: the Resource's uriKey on resource pages, and the record on a detail page. */
export interface RenderHookProps {
  resource?: string;
  record?: { id: string | number };
}

export interface RenderHookEntry {
  component: ComponentOrLazy<RenderHookProps>;
  resources: string[] | undefined;
}

const pluginPages = new Map<string, ComponentOrLazy<PluginPageProps>>();
const actionModals = new Map<string, ComponentOrLazy<ActionModalProps>>();
const pages = new Map<string, ComponentOrLazy<PageProps>>();
const fields = new Map<string, FieldRenderers>();
const filters = new Map<string, ComponentOrLazy<FilterProps>>();
const widgets = new Map<string, ComponentOrLazy<WidgetProps>>();
const resourcePlugins = new Map<string, ComponentOrLazy<ResourcePluginProps>>();
const hooks = new Map<RenderHookName, RenderHookEntry[]>();
const bootingCallbacks: (() => void)[] = [];
let booted = false;

/** Register the page shown at /lathe/plugins/{uriKey}. The last registration wins. */
export function plugin(uriKey: string, page: ComponentOrLazy<PluginPageProps>): void {
  pluginPages.set(uriKey, page);
}

export function pluginPage(uriKey: string): ComponentOrLazy<PluginPageProps> | undefined {
  return pluginPages.get(uriKey);
}

/** Register the component an action's modal(name, data) result opens. The last registration wins. */
export function actionModal(name: string, component: ComponentOrLazy<ActionModalProps>): void {
  actionModals.set(name, component);
}

export function actionModalComponent(name: string): ComponentOrLazy<ActionModalProps> | undefined {
  return actionModals.get(name);
}

/** Register the page shown at /lathe/pages/{uriKey}. The last registration wins. */
export function page(uriKey: string, component: ComponentOrLazy<PageProps>): void {
  pages.set(uriKey, component);
}

export function pageComponent(uriKey: string): ComponentOrLazy<PageProps> | undefined {
  return pages.get(uriKey);
}

/**
 * Register the components that draw a field's component key; a bare component is the form.
 * The last registration wins, and a built-in field keeps its own renderers for its key.
 */
export function field(key: string, renderers: FieldRenderers | ComponentOrLazy<FieldProps>): void {
  // A component is a function, or a lazy, memo or forwardRef object React tags with $$typeof.
  fields.set(key, typeof renderers === 'function' || '$$typeof' in renderers ? { form: renderers as ComponentOrLazy<FieldProps> } : renderers);
}

export function fieldComponent(key: string): FieldRenderers | undefined {
  return fields.get(key);
}

/** Register the component that draws a filter's component key. The last registration wins. */
export function filter(key: string, component: ComponentOrLazy<FilterProps>): void {
  filters.set(key, component);
}

export function filterComponent(key: string): ComponentOrLazy<FilterProps> | undefined {
  return filters.get(key);
}

/** Register the component that draws a widget's component key. The last registration wins. */
export function widget(key: string, component: ComponentOrLazy<WidgetProps>): void {
  widgets.set(key, component);
}

export function widgetComponent(key: string): ComponentOrLazy<WidgetProps> | undefined {
  return widgets.get(key);
}

/** Register the component a resource plugin's key draws on a detail page. The last registration wins. */
export function resourcePlugin(key: string, component: ComponentOrLazy<ResourcePluginProps>): void {
  resourcePlugins.set(key, component);
}

export function resourcePluginComponent(key: string): ComponentOrLazy<ResourcePluginProps> | undefined {
  return resourcePlugins.get(key);
}

/**
 * Add a component at a position of the panel. Every hook for a position renders, in the
 * order they were added; options.resources limits one to those Resource uriKeys.
 */
export function renderHook(position: RenderHookName, component: ComponentOrLazy<RenderHookProps>, options: { resources?: string[] } = {}): void {
  hooks.set(position, [...renderHooks(position), { component, resources: options.resources }]);
}

export function renderHooks(position: RenderHookName): RenderHookEntry[] {
  return hooks.get(position) ?? [];
}

/** Run a callback before first paint, or at once when the panel has already booted. */
export function booting(callback: () => void): void {
  if (booted) {
    callback();
  } else {
    bootingCallbacks.push(callback);
  }
}

export function boot(): void {
  booted = true;
  bootingCallbacks.splice(0).forEach((callback) => {
    callback();
  });
}
