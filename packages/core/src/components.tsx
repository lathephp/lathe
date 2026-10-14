import { createElement, Suspense, type ComponentType, type ReactNode } from 'react';
import type { ActionData, FilterData, LayoutBlock, ResourceDetailResponse, ResourceIndexResponse, WidgetData } from './types';

/** The index URL state, one key per param, as the filter menu reads and writes it. */
export interface IndexQuery {
  q?: string;
  sort?: string;
  page?: number;
  per_page?: number;
  filter?: Record<string, string | string[] | Record<string, string>>;
  tab?: string;
  saved_tab?: string;
  group?: string;
  trashed?: string;
}

/** The props of each component the panel lends to plugins. */
export interface PanelComponents {
  AppLayout: { children: ReactNode };
  ResourceTable: {
    resource: ResourceIndexResponse['resource'];
    table: ResourceIndexResponse['table'];
    state: ResourceIndexResponse['state'];
    loading: boolean;
    selection: {
      isSelected: (id: string | number) => boolean;
      toggle: (id: string | number) => void;
      toggleAll: (ids: (string | number)[]) => void;
    };
    /** The ?sort= of the URL, `-total_cents,placed_at`. */
    sort: string | undefined;
    onSort: (sort: string) => void;
  };
  FilterMenu: {
    resource: string;
    /** The Resource's filters endpoint, `/lathe/api/resources/orders/filters`. */
    endpoint: string;
    available: FilterData[];
    softDeletes: boolean;
    state: IndexQuery;
    visit: (changes: IndexQuery) => void;
  };
  ActionDropdown: { uriKey: string; id: string | number; actions: ActionData[] };
  DetailFieldPanel: {
    blocks: LayoutBlock[];
    /** Draws one field of a panel, by key; null when the key has no field here. */
    renderField: (key: string) => ReactNode;
    borderless?: boolean;
    /** Detail pages cut a panel at its limit(); forms show every field. */
    detail?: boolean;
    /** The record whose relation panels list its related rows. */
    parent?: { uriKey: string; id: string | number };
    detailFields?: ReadonlyMap<string, ResourceDetailResponse['fields'][number]>;
  };
  DetailHeader: {
    resource: { uriKey: string; label: string; singularLabel: string; abilities: { create: boolean }; [key: string]: unknown };
    record: ResourceDetailResponse & {
      layout: LayoutBlock[];
      abilities: { view: boolean; update: boolean; delete: boolean; replicate: boolean; restore: boolean; forceDelete: boolean };
      trashed: boolean;
    };
    actions?: { actions: ActionData[]; pivotActions: { name: string; actions: ActionData[] }[]; counts: Record<string, number> };
  };
  WidgetGrid: {
    widgets: WidgetData[];
    /** The widgets endpoint; a card's value is at {valueUrl}/{uriKey}. */
    valueUrl: string;
    params?: Record<string, string>;
    showRefreshButton?: boolean;
  };
  WidgetCard: {
    widget: WidgetData;
    /** The card's value endpoint. */
    url: string;
    params?: Record<string, string>;
  };
  /** Content that clips behind a Show Content button once it passes 10 lines. */
  Excerpt: { lines: number; open?: boolean; children: ReactNode };
}

type Registered = { [K in keyof PanelComponents]?: ComponentType<PanelComponents[K]> };

const registered: Registered = {};

/** The panel hands over its own components here, so a plugin renders the same ones. */
export function registerComponents(components: Registered): void {
  Object.assign(registered, components);
}

function proxy<K extends keyof PanelComponents>(name: K): ComponentType<PanelComponents[K]> {
  function Proxy(props: PanelComponents[K]) {
    const component = registered[name];

    if (component === undefined) {
      throw new Error(`${name} renders only inside the Lathe panel.`);
    }

    return <Suspense fallback={null}>{createElement(component, props)}</Suspense>;
  }

  Proxy.displayName = name;

  return Proxy;
}

/** The panel shell, for a custom page that draws its own layout. */
export const AppLayout = proxy('AppLayout');
export const ResourceTable = proxy('ResourceTable');
export const FilterMenu = proxy('FilterMenu');
/** The action menu of one record. */
export const ActionDropdown = proxy('ActionDropdown');
/** The panels, grids and tab groups of a detail page. */
export const DetailFieldPanel = proxy('DetailFieldPanel');
/** A detail page's title, subtitle and record actions. */
export const DetailHeader = proxy('DetailHeader');
export const WidgetGrid = proxy('WidgetGrid');
export const WidgetCard = proxy('WidgetCard');
export const Excerpt = proxy('Excerpt');
