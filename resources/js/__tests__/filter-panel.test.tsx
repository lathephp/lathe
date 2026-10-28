import { router } from '@inertiajs/react';
import { QueryClientProvider, queryClient } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FilterPanel } from '../index/FilterPanel';
import type { AvailableFilter } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
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
  available?: AvailableFilter[];
  softDeletes?: boolean;
  state?: IndexUrlState;
}

/** The panel as a relation table draws it, with a spy for its visits. */
function renderPanel(options: Options = {}) {
  const visit = vi.fn();
  const view = render(
    <FilterPanel
      resource="orders"
      endpoint="/lathe/api/resources/orders/filters"
      available={options.available ?? [status]}
      softDeletes={options.softDeletes ?? false}
      state={options.state ?? {}}
      visit={visit}
    />,
  );

  return { visit, container: view.container };
}

function renderIndex(url: string, staleFilters: string[]) {
  return renderPage(
    'Resources/Index',
    {
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
      filters: { available: [status], applied: {} },
      staleFilters,
      count: null,
    },
    { url },
  );
}

function mockVisit() {
  return vi.spyOn(router, 'visit').mockImplementation(() => undefined);
}

function visitData(visit: ReturnType<typeof mockVisit>, call = 0): unknown {
  return (visit.mock.calls[call]?.[1] as { data: unknown }).data;
}

describe('filter panel', () => {
  it('visits with the chosen status on its own', async () => {
    const { visit } = renderPanel();

    await choose(screen.getByLabelText('Status'), 'Pending');

    await vi.waitFor(() => {
      expect(visit).toHaveBeenCalledTimes(1);
    });
    expect(visit).toHaveBeenCalledWith({ filter: { status: 'pending' } });
  });

  it('visits on each change of an immediate filter', async () => {
    const { visit } = renderPanel({ available: [status, region], state: { filter: { status: 'paid' } } });

    await choose(screen.getByLabelText('Region'), 'Asia');

    expect(visit).toHaveBeenCalledWith({ filter: { status: 'paid', region: 'asia' } });
  });

  it('visits trashed=only for Only trashed on a soft-deleting Resource', async () => {
    const { visit } = renderPanel({ softDeletes: true });

    expect(await optionLabels(screen.getByLabelText('Trashed'))).toEqual(['Without trashed', 'With trashed', 'Only trashed']);

    await choose(screen.getByLabelText('Trashed'), 'Only trashed');

    expect(visit).toHaveBeenCalledWith({ trashed: 'only', filter: {} });
  });

  it('offers no trashed choice when the Resource does not soft delete', () => {
    renderPanel();

    expect(screen.queryByLabelText('Trashed')).toBeNull();
  });

  it('shows a link with a removed filter as out of date, and Reset drops that key', () => {
    const visit = mockVisit();
    renderIndex('/lathe/resources/orders?filter[status]=paid&filter[gone]=1', ['gone']);

    const banner = screen.getByRole('alert');

    expect(banner.textContent).toContain('This link is out of date');

    fireEvent.click(within(banner).getByRole('button', { name: 'Reset' }));

    expect(visitData(visit)).toEqual({ filter: { status: 'paid' } });
  });
});

describe('date filter', () => {
  const placed: AvailableFilter = { ...status, key: 'placed', name: 'Placed', component: 'date-filter', options: null };

  it('takes a typed day in each side and draws no native date input', async () => {
    const { visit, container } = renderPanel({ available: [placed] });

    expect(container.querySelector('input[type=date]')).toBeNull();

    const from = screen.getByLabelText('From');
    fireEvent.change(from, { target: { value: 'Oct 17, 2026' } });
    fireEvent.blur(from);

    await vi.waitFor(() => {
      expect(visit).toHaveBeenCalledTimes(1);
    });
    expect(visit).toHaveBeenCalledWith({ filter: { placed: { from: '2026-10-17' } } });
  });
});

describe('remote picker', () => {
  it('applies a picked option whose key the server sends as a number', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ options: [{ label: 'Anna Kowalski', value: 2 }], hasMore: false })));
    const visit = vi.fn();

    render(
      <QueryClientProvider client={queryClient}>
        <FilterPanel resource="orders" endpoint="/lathe/api/resources/orders/filters" available={[customer]} softDeletes={false} state={{}} visit={visit} />
      </QueryClientProvider>,
    );

    const option = await screen.findByRole('button', { name: 'Anna Kowalski' });
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
    fireEvent.click(option);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(300);
    });
    vi.useRealTimers();

    expect(visit).toHaveBeenCalledWith({ filter: { customer: '2' } });
    expect(screen.getByRole('button', { name: 'Anna Kowalski' }).getAttribute('aria-pressed')).toBe('true');
  });

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
