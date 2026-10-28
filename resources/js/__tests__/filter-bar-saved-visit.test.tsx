import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { IndexFilter } from '../index/types';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

const status: IndexFilter = {
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
  key: 'status',
  name: 'Status',
  component: 'multi-select-filter',
  options: [{ label: 'Pending', value: 'pending' }],
  token: 'status',
  kind: 'multiple',
};

/** Orders, on the saved view 5 when `open`. */
function orders(open: boolean) {
  return {
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
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    filters: { available: [status], applied: {}, labels: {} },
    staleFilters: [],
    count: null,
    tabs: {
      items: [],
      active: null,
      activeSaved: open ? 5 : null,
      saved: [{ id: 5, name: 'Big pending', badgeType: null, showCount: false, stale: false, filters: { status: ['pending'] }, q: 'ORD', sort: null }],
    },
  };
}

describe('the filter bar after opening a saved view from the rail', () => {
  it("shows the view's chips and search", async () => {
    renderPage('Resources/Index', orders(false), { url: '/lathe/resources/orders' });

    fireEvent.click(within(screen.getByRole('navigation', { name: 'Views' })).getByRole('button', { name: 'Big pending' }));
    await vi.waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    act(() => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: orders(true), url: '/lathe/resources/orders?saved_tab=5' });
    });

    await vi.waitFor(() => {
      expect(within(document.querySelector<HTMLElement>('[data-lathe-filter-bar]') as HTMLElement).getByText('Status: Pending')).toBeTruthy();
    });
    expect(screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' }).value).toBe('ORD');
  });
});
