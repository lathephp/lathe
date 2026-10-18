import { router } from '@inertiajs/react';
import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, expect, it, vi } from 'vitest';
import { FilterPanel as LazyFilterPanel } from '../index/lazy';
import type { AvailableFilter } from '../index/types';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const total: AvailableFilter = {
  key: 'total',
  name: 'Total',
  component: 'range-filter',
  options: null,
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
};

const props = {
  resource: {
    uriKey: 'orders',
    label: 'Orders',
    singularLabel: 'Order',
    perPageOptions: [25, 50, 100],
    searchable: false,
    softDeletes: false,
    abilities: { create: false },
    definition: { debounce: 0.5 },
  },
  table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
  state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
  filters: { available: [total], applied: {} },
  staleFilters: [],
  count: null,
};

let requests: FakeRequest[];

beforeAll(async () => {
  await LazyFilterPanel.preload();
});

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

it('keeps the typed range and its focus across the visit the panel sent', async () => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
  renderPage('Resources/Index', props, { url: '/lathe/resources/orders' });

  fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
  const min = screen.getByLabelText<HTMLInputElement>('Min');
  min.focus();
  fireEvent.change(min, { target: { value: '100' } });

  await act(async () => {
    await vi.advanceTimersByTimeAsync(300);
  });

  expect(requests).toHaveLength(1);
  expect(decodeURIComponent(requests[0]?.url ?? '')).toContain('filter[total][min]=100');

  // The person types on while the visit is out.
  fireEvent.change(min, { target: { value: '1005' } });

  await act(async () => {
    requests[0]?.respond(200, { component: 'Resources/Index', props, url: '/lathe/resources/orders?filter[total][min]=100' });
    await vi.advanceTimersByTimeAsync(0);
  });

  // The page took the new URL: the button counts its filter.
  expect(screen.getByRole('button', { name: 'Filter 1' })).toBeTruthy();

  const after = screen.getByLabelText<HTMLInputElement>('Min');

  expect(after.value).toBe('1005');
  expect(document.activeElement).toBe(after);
});
