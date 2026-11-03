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

const total: IndexFilter = {
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
  key: 'total',
  name: 'Total',
  component: 'range-filter',
  options: null,
  scale: 100,
  strict: true,
  token: 'total',
  kind: 'range',
};

/** Orders, on the saved view 5 when `open`; the view's filters come in the server's cleaned shape. */
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
    filters: { available: [total], applied: {}, labels: {} },
    staleFilters: [],
    count: null,
    tabs: {
      items: [],
      active: null,
      activeSaved: open ? 5 : null,
      saved: [{ id: 5, name: 'Big orders', badgeType: null, showCount: false, stale: false, filters: { total: { min: null, max: null, gt: 150000, lt: null } }, q: '', sort: null }],
    },
  };
}

const bar = () => within(document.querySelector<HTMLElement>('[data-lathe-filter-bar]') as HTMLElement);

describe('a strict range side read back by the filter bar', () => {
  it('shows > for filter[total][gt] in the URL the server answers with', async () => {
    renderPage('Resources/Index', orders(false), { url: '/lathe/resources/orders' });

    act(() => {
      router.visit('/lathe/resources/orders?filter[total][gt]=150000');
    });
    await vi.waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    act(() => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: orders(false), url: '/lathe/resources/orders?filter[total][gt]=150000' });
    });

    await vi.waitFor(() => {
      expect(bar().getByText('Total: > 1,500')).toBeTruthy();
    });
  });

  it('shows > for a saved view whose filter has a gt side', async () => {
    renderPage('Resources/Index', orders(false), { url: '/lathe/resources/orders' });

    fireEvent.click(within(screen.getByRole('navigation', { name: 'Views' })).getByRole('button', { name: 'Big orders' }));
    await vi.waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    act(() => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: orders(true), url: '/lathe/resources/orders?saved_tab=5' });
    });

    await vi.waitFor(() => {
      expect(bar().getByText('Total: > 1,500')).toBeTruthy();
    });
  });
});
