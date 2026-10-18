import { queryClient } from '@lathephp/core';
import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock, PanelBlock } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  queryClient.clear();
  vi.restoreAllMocks();
  window.history.replaceState(null, '', '/');
});

const resource = { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } };

function field(key: string, label: string, value: unknown, overrides: Record<string, unknown> = {}) {
  return {
    key,
    attribute: key,
    label,
    component: 'text-field',
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
    value,
    display: typeof value === 'string' ? value : '',
    ...overrides,
  };
}

const items = field('items', 'Items', null, {
  component: 'has-many-field',
  kind: 'hasMany',
  relationship: 'items',
  resource: 'order-items',
  singularLabel: 'Order Item',
  pluralLabel: 'Order Items',
  collapsible: true,
  collapsed: false,
  withCount: true,
  perPageOptions: null,
});

const orderFields = [
  field('number', 'Number', 'ORD-00012'),
  field('status', 'Status', 'paid', {
    component: 'select-field',
    options: [
      { value: 'pending', label: 'Pending' },
      { value: 'paid', label: 'Paid' },
    ],
    badges: { pending: 'warning', paid: 'success' },
  }),
  field('placed_at', 'Placed', 'Oct 14, 2026'),
  field('total_cents', 'Total', '$41.20'),
  field('customer', 'Customer', { value: 7, display: 'Ann Taylor', href: '/lathe/resources/customers/7' }, { component: 'belongs-to-field', resource: 'customers' }),
  items,
];

function panel(attribute: string, name: string, fields: string[], overrides: Partial<PanelBlock> = {}): PanelBlock {
  return {
    type: 'panel',
    attribute,
    name,
    fields,
    toolbar: false,
    collapsible: false,
    collapsed: false,
    hideHeading: false,
    headingInCard: false,
    help: null,
    limit: null,
    columns: null,
    columnSpan: null,
    borderless: false,
    ...overrides,
  };
}

function tab(attribute: string, label: string, blocks: LayoutBlock[]) {
  return { attribute, label, icon: null, badge: null, badgeType: null, columns: null, borderless: false, items: blocks };
}

// The layout Order.php produces: two sections, then a field tab group of Billing and Items.
const orderLayout: LayoutBlock[] = [
  panel('order', 'Order', ['number', 'status', 'placed_at']),
  panel('customer', 'Customer', ['customer']),
  {
    type: 'tab-group',
    attribute: 'tab-group-1',
    label: null,
    queryKey: 'tab',
    activeTab: 0,
    vertical: false,
    contained: false,
    columnSpan: null,
    tabs: [
      tab('billing', 'Billing', [panel('billing-fields', 'Billing', ['total_cents'], { hideHeading: true })]),
      tab('items', 'Items', [{ type: 'relation-field', key: 'items', field: items } as LayoutBlock]),
    ],
  },
];

const abilities = { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false };

/** The relation panel's endpoints: no rows, no filters, nothing to add. */
function answer() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const body = url.endsWith('/history/latest')
      ? { events: [{ id: 1, type: 'update', title: 'Changed Status', user: 'Admin User', at: '2026-10-17T21:00:00Z' }] }
      : url.includes('/relate-authorization')
      ? { items: { add: false, attach: false, attachAny: false } }
      : url.includes('/filters')
        ? { filters: [] }
        : { columns: [], sortKeys: [], rows: [], count: { value: 0, exact: true, capped: false, estimated: false }, page: 1, perPage: 25, perPageOptions: [25], hasMore: false, searchable: false };

    return Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));
  });
}

const actionEvents = field('actions', 'Action Events', null, { ...items, key: 'actions', attribute: 'actions', label: 'Action Events', kind: 'morphMany', relationship: 'actions', resource: 'action-events', collapsed: true, withCount: false });

function renderOrder(counts: Record<string, number>, extra: Record<string, unknown> = {}) {
  answer();

  return renderPage('Resources/Detail', {
    resource,
    record: {
      id: 12,
      title: 'ORD-00012',
      subtitle: '',
      header: { badge: 'status', meta: 'placed_at' },
      titleKey: 'number',
      counts,
      avatar: null,
      fields: orderFields,
      layout: orderLayout,
      abilities,
      trashed: false,
      ...extra,
    },
    breadcrumbs: [],
  });
}

describe('record columns', () => {
  it('opens the first to-many relation as a tab labelled with its count', () => {
    renderOrder({ items: 1 });

    const first = screen.getByRole('tab', { name: 'Items 1' });
    expect(screen.getAllByRole('tab')[0]).toBe(first);
    expect(first.getAttribute('aria-selected')).toBe('true');
  });

  it('shows the count 0 for a relation without rows', () => {
    renderOrder({ items: 0 });

    expect(screen.getByRole('tab', { name: 'Items 0' })).toBeTruthy();
  });

  it('lists Total in Details and leaves out the title, badge and meta fields', () => {
    renderOrder({ items: 1 });

    const details = within(screen.getByRole('region', { name: 'Details' }));
    expect(details.getByText('Total')).toBeTruthy();
    expect(details.getByText('$41.20')).toBeTruthy();
    expect(details.queryByText('Number')).toBeNull();
    expect(details.queryByText('Status')).toBeNull();
    expect(details.queryByText('Placed')).toBeNull();
    expect(details.queryByText('Customer')).toBeNull();
  });

  it('shows the customer in a card of its own, linked to its page', () => {
    renderOrder({ items: 1 });

    const side = document.querySelector<HTMLElement>('[data-detail-column="side"]');
    expect(side).not.toBeNull();
    const card = within(within(side as HTMLElement).getByRole('region', { name: 'Customer' }));
    expect(card.getByRole('link', { name: 'Ann Taylor' }).getAttribute('href')).toBe('/lathe/resources/customers/7');
  });

  it('puts Details in the main column when the record has no tabs and no wide fields', () => {
    renderPage('Resources/Detail', {
      resource,
      record: {
        id: 3,
        title: 'Ann Taylor',
        subtitle: '',
        header: { badge: null, meta: null },
        titleKey: 'name',
        counts: {},
        avatar: null,
        fields: [field('name', 'Name', 'Ann Taylor'), field('email', 'Email', 'ann@example.com')],
        layout: [panel('customer', 'Customer', ['name', 'email'])],
        abilities,
        trashed: false,
      },
      breadcrumbs: [],
    });

    const main = document.querySelector<HTMLElement>('[data-detail-column="main"]');
    expect(main).not.toBeNull();
    expect(within(main as HTMLElement).getByRole('region', { name: 'Details' })).toBeTruthy();
    expect(screen.queryAllByRole('tab')).toHaveLength(0);
  });

  it('ends the right column with the History card, whose Show all opens the Action Events tab', async () => {
    renderOrder(
      { items: 1 },
      {
        history: true,
        fields: [...orderFields, actionEvents],
        layout: [...orderLayout, { type: 'relation-field', key: 'actions', field: actionEvents } as LayoutBlock],
      },
    );

    const side = document.querySelector<HTMLElement>('[data-detail-column="side"]') as HTMLElement;
    const history = await within(side).findByRole('region', { name: 'History' });
    expect(side.lastElementChild?.contains(history)).toBe(true);
    expect(await within(history).findByText('Changed Status')).toBeTruthy();

    fireEvent.click(within(history).getByRole('button', { name: 'Show all' }));

    expect(screen.getByRole('tab', { name: 'Action Events' }).getAttribute('aria-selected')).toBe('true');
  });

  it('shows no History card when the record keeps no log the viewer may read', () => {
    renderOrder({ items: 1 }, { history: false });

    expect(screen.queryByRole('heading', { name: 'History' })).toBeNull();
  });
});
