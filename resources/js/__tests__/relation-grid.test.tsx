import { router } from '@inertiajs/react';
import { queryClient } from '@lathephp/core';
import { act, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock } from '../fields/types';
import { clearUndo } from '../index/undo';
import { cellOf, editCell, pick, renderPage } from './support';

afterEach(() => {
  queryClient.clear();
  clearUndo();
  vi.restoreAllMocks();
  window.history.replaceState(null, '', '/');
});

const ordersField = {
  key: 'orders',
  attribute: 'orders',
  label: 'Orders',
  component: 'has-many-field',
  sortable: false,
  computed: false,
  nullable: false,
  meta: {},
  collapsible: false,
  collapsed: false,
  perPageOptions: null,
  withCount: false,
  kind: 'hasMany',
  relationship: 'orders',
  resource: 'orders',
  singularLabel: 'Order',
  pluralLabel: 'Orders',
};

const columns = [
  { key: 'number', attribute: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number', textAlign: 'left' },
  {
    key: 'status',
    attribute: 'status',
    label: 'Status',
    component: 'select-field',
    sortable: false,
    sortKey: 'status',
    textAlign: 'left',
    inline: true,
    nullable: false,
    options: ['Pending', 'Paid', 'Shipped', 'Cancelled'].map((label) => ({ value: label.toLowerCase(), label })),
    badges: { pending: 'warning', paid: 'info', shipped: 'success', cancelled: 'neutral' },
  },
];

const order = (id: number) => ({
  id,
  title: `ORD-000${String(id)}`,
  cells: { number: { value: `ORD-000${String(id)}`, display: `ORD-000${String(id)}` }, status: { value: 'pending', display: 'Pending' } },
  abilities: { view: true, update: true, inlineEdit: true },
  related_id: id,
  updatedAt: `2026-10-14T09:00:${String(id)}.000Z`,
});

/** Answers the panel's endpoints and a cell's save, whose order then reads Paid, and records every request. */
function answer() {
  const rows = [order(41), order(40)];

  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;

    if (init?.method === 'PATCH') {
      rows[0] = { ...order(41), cells: { ...order(41).cells, status: { value: 'paid', display: 'Paid' } }, updatedAt: '2026-10-14T09:01:00.000Z' };
    }

    const body = init?.method === 'PATCH'
      ? { field: 'status', fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: '2026-10-14T09:01:00.000Z' }
      : url.includes('/relate-authorization')
        ? { orders: { add: false, attach: false, attachAny: false } }
        : url.includes('/filters')
          ? { filters: [] }
          : { columns, sortKeys: [], rows, count: null, page: 1, perPage: 25, perPageOptions: [25], hasMore: false, searchable: false };

    return Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));
  });
}

function renderCustomer() {
  return renderPage('Resources/Detail', {
    resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } },
    record: {
      id: 7,
      title: 'Ann Lee',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [{ type: 'relation-field', key: 'orders', field: ordersField } as unknown as LayoutBlock],
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
    },
    breadcrumbs: [],
  });
}

describe("a customer's Orders table", () => {
  it('is a grid of one tab stop whose Status cell edits the order in place', async () => {
    const fetch = answer();
    renderCustomer();
    await screen.findByText('ORD-00041');

    const grid = screen.getByRole('grid');
    expect(grid.querySelectorAll('[tabindex="0"]')).toHaveLength(1);

    await editCell('Status');
    expect(await screen.findAllByRole('option')).toHaveLength(4);
    await pick('Paid');

    await waitFor(() => {
      expect(cellOf('Status').textContent).toBe('Paid');
    });
    const patch = fetch.mock.calls.find((call) => call[1]?.method === 'PATCH');
    expect(patch?.[0]).toBe('/lathe/api/resources/orders/41/field/status');
    expect(JSON.parse(patch?.[1]?.body as string)).toEqual({ value: 'paid', _updated_at: '2026-10-14T09:00:41.000Z' });
  });

  it('moves the cursor with the arrows and opens the order from Enter on its Number', async () => {
    answer();
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderCustomer();
    await screen.findByText('ORD-00041');

    await editCell('Number', 'ArrowDown');
    expect(document.activeElement).toBe(cellOf('Number', 1));
    act(() => {
      cellOf('Number', 1).focus();
    });
    await editCell('Number', 'Enter', 1);

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/40');
  });
});
