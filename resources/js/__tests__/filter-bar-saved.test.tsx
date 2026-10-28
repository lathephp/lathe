import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { IndexFilter } from '../index/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const base = { remote: false, immediate: false, placement: 'panel', default: null };

const status: IndexFilter = {
  ...base,
  key: 'status',
  name: 'Status',
  component: 'multi-select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
  ],
  token: 'status',
  kind: 'multiple',
};

const total: IndexFilter = { ...base, key: 'field_total_cents', name: 'Total', component: 'range-filter', options: null, scale: 100, token: 'total', kind: 'range' };

const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', component: 'select-filter', options: null, remote: true, token: 'customer', kind: 'relation' };

/** Orders opened on the saved view "Big pending", whose filters come back as the server cleans them. */
function renderSavedView(url = '/lathe/resources/orders?saved_tab=5') {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: true,
        softDeletes: false,
        abilities: { create: false },
        definition: { debounce: 0.5, savedTabs: true },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '-total_cents' },
      filters: { available: [status, customer, total], applied: {}, labels: { customer: { '12': 'Ann Taylor' } } },
      staleFilters: [],
      count: null,
      tabs: {
        items: [],
        active: null,
        activeSaved: 5,
        saved: [
          {
            id: 5,
            name: 'Big pending',
            badgeType: null,
            showCount: false,
            stale: false,
            filters: { status: ['pending'], field_total_cents: { min: 150000, max: null }, customer: '12' },
            q: 'ORD',
            sort: '-total_cents',
          },
        ],
      },
    },
    { url },
  );
}

function bar(): HTMLElement {
  return document.querySelector<HTMLElement>('[data-lathe-filter-bar]') as HTMLElement;
}

describe('filter bar on a saved view', () => {
  it("draws the view's own filters as chips and its search as the text", () => {
    renderSavedView();

    expect(within(bar()).getByText('Status: Pending')).toBeTruthy();
    expect(within(bar()).getByText('Total: ≥ 1,500')).toBeTruthy();
    expect(within(bar()).getByText('Customer: Ann Taylor')).toBeTruthy();
    expect(screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' }).value).toBe('ORD');
  });

  it('leaves the view with the rest of its chips, search and sort when a chip goes', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderSavedView();

    act(() => {
      fireEvent.click(within(bar()).getByRole('button', { name: 'Remove Status filter' }));
    });

    expect(visit).toHaveBeenCalledTimes(1);
    expect((visit.mock.calls[0]?.[1] as { data: unknown }).data).toEqual({
      filter: { field_total_cents: { min: '150000' }, customer: '12' },
      q: 'ORD',
      sort: '-total_cents',
    });
  });
});
