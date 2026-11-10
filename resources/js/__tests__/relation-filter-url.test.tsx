import { queryClient } from '@lathephp/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock } from '../fields/types';
import { indexTable, renderPage } from './support';

afterEach(() => {
  queryClient.clear();
  vi.restoreAllMocks();
  window.history.replaceState(null, '', '/');
});

const status = {
  key: 'status',
  name: 'Status',
  component: 'multi-select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
  ],
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
  token: 'status',
  kind: 'multiple',
};

function ordersField(key: string, label: string) {
  return {
    type: 'relation-field',
    key,
    field: {
      key,
      attribute: key,
      label,
      component: 'has-many-field',
      sortable: false,
      collapsible: false,
      collapsed: false,
      perPageOptions: null,
      withCount: false,
      kind: 'hasMany',
      relationship: key,
      resource: 'orders',
      singularLabel: 'Order',
      pluralLabel: label,
      meta: {},
    },
  } as unknown as LayoutBlock;
}

/** A customer's two order lists, read from the server's own orders table. */
function answer() {
  const { columns, rows } = indexTable('orders');
  const reply = { columns, sortKeys: [], rows: rows.map((row) => ({ ...row, related_id: row.id })), count: null, page: 1, perPage: 25, perPageOptions: [25], hasMore: false, searchable: true };

  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const body = url.includes('/relate-authorization') ? {} : url.includes('/filters') ? { filters: [status] } : reply;

    return Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));
  });
}

describe('a relation tab\'s filter bar', () => {
  it('keeps its chips out of the page URL', async () => {
    window.history.replaceState(null, '', '/lathe/resources/customers/7');
    const fetch = answer();
    renderPage(
      'Resources/Detail',
      {
        resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: false } },
        record: { id: 7, title: 'Ann', subtitle: '', avatar: null, fields: [], layout: [ordersField('orders', 'Orders'), ordersField('returns', 'Returns')], abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
        breadcrumbs: [],
      },
      { url: '/lathe/resources/customers/7' },
    );
    const panel = screen.getByRole('tabpanel', { name: 'Orders' });
    await waitFor(() => {
      expect(within(panel).getAllByRole('row').length).toBeGreaterThan(1);
    });

    act(() => {
      within(panel).getByRole('searchbox', { name: 'Search orders' }).focus();
    });
    const input = await within(panel).findByRole<HTMLInputElement>('combobox', { name: 'Search orders' });
    fireEvent.change(input, { target: { value: 'status:pending' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    await waitFor(() => {
      expect(fetch.mock.calls.some(([url]) => typeof url === 'string' && decodeURIComponent(url).endsWith('/orders?filter[status]=pending'))).toBe(true);
    });

    expect(window.location.pathname + window.location.search).toBe('/lathe/resources/customers/7');
  });
});
