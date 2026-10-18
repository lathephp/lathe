import { router } from '@inertiajs/react';
import { QueryClientProvider, queryClient } from '@lathephp/core';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/FilterPanel';
import type { AvailableFilter } from '../index/types';
import { choose, optionLabels, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  queryClient.clear();
});

const status: AvailableFilter = {
  key: 'status',
  name: 'Status',
  component: 'select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
  ],
  remote: false,
  immediate: false,
  placement: 'panel',
  default: null,
};

const region: AvailableFilter = {
  ...status,
  key: 'region',
  name: 'Region',
  options: [
    { label: 'Europe', value: 'eu' },
    { label: 'Asia', value: 'asia' },
  ],
  immediate: true,
};

const customer: AvailableFilter = {
  ...status,
  key: 'customer',
  name: 'Customer',
  options: null,
  remote: true,
};

interface Options {
  url?: string;
  available?: AvailableFilter[];
  softDeletes?: boolean;
  staleFilters?: string[];
}

function renderIndex(options: Options = {}) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: false,
        softDeletes: options.softDeletes ?? false,
        abilities: { create: false },
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available: options.available ?? [status], applied: {} },
      staleFilters: options.staleFilters ?? [],
      count: null,
    },
    { url: options.url ?? '/lathe/resources/orders' },
  );
}

function mockVisit() {
  return vi.spyOn(router, 'visit').mockImplementation(() => undefined);
}

function visitData(visit: ReturnType<typeof mockVisit>, call = 0): unknown {
  return (visit.mock.calls[call]?.[1] as { data: unknown }).data;
}

describe('filter panel', () => {
  it('changes nothing until Apply, then visits with the chosen status', async () => {
    const visit = mockVisit();
    renderIndex({ url: '/lathe/resources/orders?page=3' });

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    await choose(screen.getByLabelText('Status'), 'Pending');

    expect(visit).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Apply' }));

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'pending' } });
  });

  it('visits on each change of an immediate filter', async () => {
    const visit = mockVisit();
    renderIndex({ available: [status, region], url: '/lathe/resources/orders?filter[status]=paid' });

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    await choose(screen.getByLabelText('Region'), 'Asia');

    expect(visitData(visit)).toEqual({ filter: { status: 'paid', region: 'asia' } });
  });

  it('visits trashed=only for Only trashed on a soft-deleting Resource', async () => {
    const visit = mockVisit();
    renderIndex({ softDeletes: true });

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }));
    expect(await optionLabels(screen.getByLabelText('Trashed'))).toEqual(['Without trashed', 'With trashed', 'Only trashed']);

    await choose(screen.getByLabelText('Trashed'), 'Only trashed');

    expect(visitData(visit)).toEqual({ trashed: 'only' });
  });

  it('offers no trashed choice when the Resource does not soft delete', () => {
    renderIndex();

    fireEvent.click(screen.getByRole('button', { name: 'Filters' }));

    expect(screen.queryByLabelText('Trashed')).toBeNull();
  });

  it('shows a link with a removed filter as out of date, and Reset drops that key', () => {
    const visit = mockVisit();
    renderIndex({ url: '/lathe/resources/orders?filter[status]=paid&filter[gone]=1', staleFilters: ['gone'] });

    const banner = screen.getByRole('alert');

    expect(banner.textContent).toContain('This link is out of date');

    fireEvent.click(within(banner).getByRole('button', { name: 'Reset' }));

    expect(visitData(visit)).toEqual({ filter: { status: 'paid' } });
  });
});

describe('remote picker', () => {
  it('pages the options from the server on More and lists every label', async () => {
    const pages: Record<string, unknown> = {
      '1': { options: [{ label: 'Ann Lee', value: '1' }], hasMore: true },
      '2': { options: [{ label: 'Bob Ray', value: '2' }], hasMore: false },
    };
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const page = new URL(input as string, 'http://localhost').searchParams.get('page') ?? '';

      return Promise.resolve(new Response(JSON.stringify(pages[page])));
    });

    render(
      <QueryClientProvider client={queryClient}>
        <FilterPanel
          resource="orders"
          endpoint="/lathe/api/resources/orders/filters"
          available={[customer]}
          softDeletes={false}
          state={{}}
          visit={() => undefined}
        />
      </QueryClientProvider>,
    );

    expect(await screen.findByRole('button', { name: 'Ann Lee' })).toBeTruthy();
    expect(fetch.mock.calls.map(([input]) => input as string)).toEqual([
      '/lathe/api/resources/orders/filters/customer/options?q=&page=1',
    ]);

    fireEvent.click(screen.getByRole('button', { name: 'More' }));

    expect(await screen.findByRole('button', { name: 'Bob Ray' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Ann Lee' })).toBeTruthy();
    expect(fetch.mock.calls.map(([input]) => input as string)).toEqual([
      '/lathe/api/resources/orders/filters/customer/options?q=&page=1',
      '/lathe/api/resources/orders/filters/customer/options?q=&page=2',
    ]);
    expect(screen.queryByRole('button', { name: 'More' })).toBeNull();
  });
});
