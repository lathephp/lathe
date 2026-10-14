import { router } from '@inertiajs/react';
import { queryClient } from '@lathephp/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock, PanelBlock } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  queryClient.clear();
  vi.restoreAllMocks();
  vi.useRealTimers();
  window.history.replaceState(null, '', '/');
});

const resource = { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } };

function itemsField(overrides: Record<string, unknown> = {}) {
  return {
    key: 'items',
    attribute: 'items',
    label: 'Items',
    component: 'has-many-field',
    sortable: false,
    computed: false,
    nullable: false,
    stacked: false,
    fullWidth: false,
    columnSpan: null,
    indexWidth: null,
    borderless: false,
    compact: false,
    textAlign: 'left',
    placeholder: null,
    help: null,
    helpWidth: null,
    wrapping: false,
    section: null,
    autocomplete: null,
    meta: {},
    collapsible: false,
    collapsed: false,
    perPageOptions: null,
    withCount: false,
    kind: 'hasMany',
    relationship: 'items',
    resource: 'order-items',
    singularLabel: 'Order Item',
    pluralLabel: 'Order Items',
    ...overrides,
  };
}

function relationBlock(overrides: Record<string, unknown> = {}): LayoutBlock {
  return { type: 'relation-field', key: 'items', field: itemsField(overrides) } as LayoutBlock;
}

const billing: PanelBlock = {
  type: 'panel',
  attribute: 'billing-fields',
  name: 'Billing',
  fields: [],
  toolbar: false,
  collapsible: false,
  collapsed: false,
  hideHeading: true,
  headingInCard: false,
  help: null,
  limit: null,
  columns: null,
  columnSpan: null,
  borderless: false,
};

function inTabs(block: LayoutBlock): LayoutBlock {
  const tab = (attribute: string, label: string, items: LayoutBlock[]) => ({ attribute, label, icon: null, badge: null, badgeType: null, columns: null, borderless: false, items });

  return {
    type: 'tab-group',
    attribute: 'tab-group-1',
    label: null,
    queryKey: 'tab',
    activeTab: 0,
    vertical: false,
    contained: false,
    columnSpan: null,
    tabs: [tab('billing', 'Billing', [billing]), tab('items', 'Items', [block])],
  };
}

function renderDetail(layout: LayoutBlock[]) {
  return renderPage('Resources/Detail', {
    resource,
    record: { id: 12, title: 'ORD-00012', subtitle: '', avatar: null, fields: [], layout, abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
    breadcrumbs: [],
  });
}

const columns = [
  { key: 'product', attribute: 'product', label: 'Product', component: 'text-field', sortable: false, sortKey: 'product.name', textAlign: 'left' },
  { key: 'quantity', attribute: 'quantity', label: 'Quantity', component: 'text-field', sortable: true, sortKey: 'quantity', textAlign: 'right' },
];

function row(id: number, product: string, quantity: number) {
  return {
    id,
    title: String(id),
    cells: { product: { value: product, display: product }, quantity: { value: quantity, display: String(quantity) } },
    abilities: { view: true },
    related_id: id,
  };
}

const items = [row(41, 'Autem blanditiis', 2), row(40, 'Ea ducimus', 4)];

interface Reply {
  rows?: ReturnType<typeof row>[];
  count?: { value: number; exact: boolean; capped: boolean; estimated: boolean };
  add?: boolean;
}

/** Answers the panel's three endpoints and records every URL asked for. */
function answer({ rows = items, count = { value: 7, exact: true, capped: false, estimated: false }, add = true }: Reply = {}) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const body = url.includes('/relate-authorization')
      ? { items: { add, attach: false, attachAny: false } }
      : url.includes('/filters')
        ? { filters: [] }
        : { columns, sortKeys: ['quantity'], rows, count, page: 1, perPage: 25, perPageOptions: [25, 50, 100], hasMore: false };

    return Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));
  });
}

function listCalls(fetch: ReturnType<typeof answer>): string[] {
  return fetch.mock.calls
    .map(([input]) => (typeof input === 'string' ? input : ''))
    .filter((url) => url.startsWith('/lathe/api/resources/orders/12/items'));
}

async function settle() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 20));
  });
}

describe('relation panel', () => {
  it('loads its rows once when it is on screen', async () => {
    const fetch = answer();
    renderDetail([relationBlock()]);

    expect(await screen.findByText('Autem blanditiis')).toBeTruthy();
    expect(screen.getByText('Ea ducimus')).toBeTruthy();
    expect(listCalls(fetch)).toEqual(['/lathe/api/resources/orders/12/items']);
  });

  it('waits for its tab to open before it loads', async () => {
    const fetch = answer();
    renderDetail([inTabs(relationBlock())]);
    await settle();

    expect(listCalls(fetch)).toEqual([]);

    fireEvent.click(screen.getByRole('tab', { name: 'Items' }));

    expect(await screen.findByText('Autem blanditiis')).toBeTruthy();
    expect(listCalls(fetch)).toEqual(['/lathe/api/resources/orders/12/items']);
  });

  it('loads at once when the URL opens its tab', async () => {
    window.history.replaceState(null, '', '/lathe/resources/orders/12?tab=items');
    const fetch = answer();
    renderDetail([inTabs(relationBlock())]);

    expect(await screen.findByText('Autem blanditiis')).toBeTruthy();
    expect(listCalls(fetch)).toEqual(['/lathe/api/resources/orders/12/items']);
  });

  it('sorts by a column header through the endpoint, without a page visit', async () => {
    const fetch = answer();
    const visit = vi.spyOn(router, 'visit');
    const get = vi.spyOn(router, 'get');
    renderDetail([relationBlock()]);
    await screen.findByText('Autem blanditiis');

    fireEvent.click(screen.getByRole('button', { name: 'Quantity' }));
    await waitFor(() => {
      expect(listCalls(fetch).at(-1)).toBe('/lathe/api/resources/orders/12/items?sort=quantity');
    });
    fireEvent.click(screen.getByRole('button', { name: 'Quantity' }));
    await waitFor(() => {
      expect(listCalls(fetch).at(-1)).toBe('/lathe/api/resources/orders/12/items?sort=-quantity');
    });

    expect(visit).not.toHaveBeenCalled();
    expect(get).not.toHaveBeenCalled();
  });

  it('searches after the typing stops', async () => {
    const fetch = answer();
    renderDetail([relationBlock()]);
    await screen.findByText('Autem blanditiis');

    fireEvent.change(screen.getByRole('searchbox', { name: 'Search items' }), { target: { value: 'ea' } });

    expect(listCalls(fetch)).toHaveLength(1);
    await waitFor(() => {
      expect(listCalls(fetch).at(-1)).toBe('/lathe/api/resources/orders/12/items?q=ea');
    });
  });

  it('asks for another page size from the footer', async () => {
    const fetch = answer();
    renderDetail([relationBlock()]);
    await screen.findByText('Autem blanditiis');

    fireEvent.change(screen.getByRole('combobox', { name: 'Per page' }), { target: { value: '50' } });

    await waitFor(() => {
      expect(listCalls(fetch).at(-1)).toBe('/lathe/api/resources/orders/12/items?per_page=50');
    });
  });

  it('loads a collapsed panel only once it is unfolded', async () => {
    const fetch = answer();
    renderDetail([relationBlock({ collapsible: true, collapsed: true })]);
    await settle();

    expect(listCalls(fetch)).toEqual([]);

    fireEvent.click(screen.getByRole('button', { name: 'Items' }));

    expect(await screen.findByText('Autem blanditiis')).toBeTruthy();
    expect(listCalls(fetch)).toEqual(['/lathe/api/resources/orders/12/items']);
  });

  it('shows the count in the header with withCount', async () => {
    answer();
    renderDetail([relationBlock({ withCount: true })]);

    const heading = screen.getByRole('heading', { name: /Items/ });
    expect(await within(heading).findByText('7')).toBeTruthy();
  });

  it('shows a capped count with a plus', async () => {
    answer({ count: { value: 10000, exact: false, capped: true, estimated: false } });
    renderDetail([relationBlock({ withCount: true })]);

    const heading = screen.getByRole('heading', { name: /Items/ });
    expect(await within(heading).findByText('10,000+')).toBeTruthy();
  });

  it('offers Create on an empty panel when the user may add', async () => {
    answer({ rows: [] });
    renderDetail([relationBlock()]);

    expect(await screen.findByText('No Items yet')).toBeTruthy();
    await waitFor(() => {
      expect(screen.getByRole('link', { name: 'Create' }).getAttribute('href')).toBe('/lathe/resources/order-items/new?via=orders/12/items');
    });
  });

  it('hides Create when the user may not add', async () => {
    const fetch = answer({ rows: [], add: false });
    renderDetail([relationBlock()]);

    expect(await screen.findByText('No Items yet')).toBeTruthy();
    await waitFor(() => {
      expect(fetch.mock.calls.some(([input]) => typeof input === 'string' && input.includes('/relate-authorization'))).toBe(true);
    });
    await settle();

    expect(screen.queryByRole('link', { name: 'Create' })).toBeNull();
  });

  it.each(['hasManyThrough', 'hasOneThrough', 'belongsToMany', 'morphToMany', 'morphedByMany'])('offers no Create for a %s panel', async (kind) => {
    answer({ rows: [] });
    renderDetail([relationBlock({ kind })]);

    expect(await screen.findByText('No Items yet')).toBeTruthy();
    await settle();

    expect(screen.queryByRole('link', { name: 'Create' })).toBeNull();
  });
});
