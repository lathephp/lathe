import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { IndexFilter } from '../index/types';
import { renderPage } from './support';

afterEach(() => {
  vi.useRealTimers();
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

const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', component: 'select-filter', options: null, remote: true, token: 'customer', kind: 'relation' };

const total: IndexFilter = { ...base, key: 'field_total_cents', name: 'Total', component: 'range-filter', options: null, scale: 100, token: 'total', kind: 'range' };

function renderOrders(url = '/lathe/resources/orders') {
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
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available: [status, customer, total], applied: {}, labels: { customer: { '12': 'Ann Taylor' } } },
      staleFilters: [],
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

function bar(): HTMLElement {
  return document.querySelector<HTMLElement>('[data-lathe-filter-bar]') as HTMLElement;
}

/** Focuses the bar as a person does, waits for its editor on the real clock, then runs on a fake one. */
async function focusBar(): Promise<HTMLInputElement> {
  act(() => {
    screen.getByRole('searchbox', { name: 'Search orders' }).focus();
  });
  const input = await screen.findByRole<HTMLInputElement>('combobox', { name: 'Search orders' });
  expect(document.activeElement).toBe(input);
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

  return input;
}

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

async function enter(text: string) {
  const input = document.activeElement as HTMLInputElement;
  fireEvent.change(input, { target: { value: text } });
  fireEvent.keyDown(input, { key: 'Enter' });
  await advance(0);
}

describe('filter bar', () => {
  it('lets a good chip replace the red chip its filter had', async () => {
    mockVisit();
    renderOrders();
    await focusBar();

    await enter('status:');
    expect(within(bar()).getByText('Status needs a value')).toBeTruthy();

    await enter('status: paid');

    expect(within(bar()).getByText('Status: Paid')).toBeTruthy();
    expect(within(bar()).queryByText('Status needs a value')).toBeNull();
  });

  it('keeps a chip just made when words typed after it search', async () => {
    const visit = mockVisit();
    renderOrders();
    const input = await focusBar();

    await enter('status:paid');
    fireEvent.change(input, { target: { value: 'ann' } });
    await advance(600);

    expect(visit).toHaveBeenCalledTimes(2);
    expect(visitData(visit, 1)).toEqual({ filter: { status: 'paid' }, q: 'ann' });
  });

  it('drops the search a half-typed key started once the token lands as a chip', async () => {
    const visit = mockVisit();
    renderOrders('/lathe/resources/orders?q=stat');
    await focusBar();

    await enter('status:pending');
    await advance(300);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'pending' } });
  });

  it('turns status:pending into a chip and visits once, 300 ms later', async () => {
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('status:pending');

    expect(within(bar()).getByText('Status: Pending')).toBeTruthy();

    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'pending' } });
  });

  it('writes a range typed in dollars as cents', async () => {
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('total:>1500');
    await advance(300);

    expect(visitData(visit)).toEqual({ filter: { field_total_cents: { min: '150000' } } });

    await enter('total:10..50');
    await advance(300);

    expect(visitData(visit, 1)).toEqual({ filter: { field_total_cents: { min: '1000', max: '5000' } } });
  });

  it('looks a customer up by name and filters by her id', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ options: [{ label: 'Ann Taylor', value: 12 }, { label: 'Ann Taylorson', value: 13 }], hasMore: false })),
    );
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('customer:"Ann Taylor"');
    await advance(300);

    const url = new URL(fetch.mock.calls[0]?.[0] as string, 'http://localhost');

    expect(url.pathname).toBe('/lathe/api/resources/orders/filters/customer/options');
    expect(url.searchParams.get('q')).toBe('Ann Taylor');
    expect(url.searchParams.get('page')).toBe('1');
    expect(within(bar()).getByText('Customer: Ann Taylor')).toBeTruthy();
    expect(visitData(visit)).toEqual({ filter: { customer: '12' } });
  });

  it('searches plain words as q', async () => {
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('ann');
    await advance(500);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ q: 'ann' });
  });

  it('sends two statuses as a list', async () => {
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('status:pending,paid');
    await advance(300);

    expect(visitData(visit)).toEqual({ filter: { status: ['pending', 'paid'] } });
    expect(within(bar()).getByText('Status: Pending, Paid')).toBeTruthy();
  });

  it('shows an unknown key and a second customer as red chips and sends nothing', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('statuz:paid');
    await enter('customer:"Ann Taylor","Ada Quinn"');
    await advance(1000);

    const unknown = within(bar()).getByText('No filter named statuz').closest('[data-error]');
    const twice = within(bar()).getByText('Customer takes one value').closest('[data-error]');

    expect(unknown).not.toBeNull();
    expect(twice).not.toBeNull();
    expect(visit).not.toHaveBeenCalled();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('draws the chips of an old link before any focus, a customer by her name', () => {
    renderOrders('/lathe/resources/orders?filter[status]=paid&filter[customer]=12');

    expect(within(bar()).getByText('Status: Paid')).toBeTruthy();
    expect(within(bar()).getByText('Customer: Ann Taylor')).toBeTruthy();
    expect(screen.queryByRole('combobox')).toBeNull();
  });

  it('removes the last chip on Backspace in the empty input and visits without it', async () => {
    const visit = mockVisit();
    renderOrders('/lathe/resources/orders?filter[status]=paid&filter[field_total_cents][min]=150000');
    const input = await focusBar();

    fireEvent.keyDown(input, { key: 'Backspace' });
    await advance(300);

    expect(within(bar()).queryByText('Total: ≥ 1,500')).toBeNull();
    expect(within(bar()).getByText('Status: Paid')).toBeTruthy();
    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'paid' } });
  });

  it('sends two chips added within 300 ms as one visit', async () => {
    const visit = mockVisit();
    renderOrders();
    await focusBar();

    await enter('status:paid');
    await advance(100);
    await enter('total:>1500');
    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'paid', field_total_cents: { min: '150000' } } });
  });

  it('names each chip\'s remove button after its filter', async () => {
    const visit = mockVisit();
    renderOrders('/lathe/resources/orders?filter[status]=paid');
    await focusBar();

    fireEvent.click(within(bar()).getByRole('button', { name: 'Remove Status filter' }));
    await advance(300);

    expect(visitData(visit)).toEqual({});
  });

  it('replaces the Filter button and the chip row', () => {
    renderOrders('/lathe/resources/orders?filter[status]=paid');

    expect(screen.queryByRole('button', { name: /^Filter/ })).toBeNull();
    expect(document.querySelector('[data-lathe-chips]')).toBeNull();
    expect(document.querySelector('[data-lathe-toolbar]')?.contains(bar())).toBe(true);
  });
});
