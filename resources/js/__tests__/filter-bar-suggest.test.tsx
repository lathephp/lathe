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
    { label: 'Shipped', value: 'shipped' },
    { label: 'Cancelled', value: 'cancelled' },
  ],
  token: 'status',
  kind: 'multiple',
};

const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', component: 'select-filter', options: null, remote: true, token: 'customer', kind: 'relation' };

const total: IndexFilter = { ...base, key: 'field_total_cents', name: 'Total', component: 'range-filter', options: null, scale: 100, token: 'total', kind: 'range' };

function renderOrders() {
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
      filters: { available: [status, customer, total], applied: {} },
      staleFilters: [],
      count: null,
    },
    { url: '/lathe/resources/orders' },
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

/** Focuses and clicks the bar as a person does, waits for its editor on the real clock, then runs on a fake one. */
async function clickBar(): Promise<HTMLInputElement> {
  const plain = screen.getByRole('searchbox', { name: 'Search orders' });
  fireEvent.pointerDown(plain);
  act(() => {
    plain.focus();
  });
  fireEvent.click(plain);
  const input = await screen.findByRole<HTMLInputElement>('combobox', { name: 'Search orders' });
  expect(document.activeElement).toBe(input);
  // Base UI mounts the open list a frame after its input; the fake clock below would hold it back.
  await screen.findAllByRole('option');
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

  return input;
}

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

async function type(text: string) {
  fireEvent.change(document.activeElement as HTMLInputElement, { target: { value: text } });
  await advance(0);
}

async function press(key: string) {
  fireEvent.keyDown(document.activeElement as HTMLElement, { key });
  await advance(0);
}

/** Clicks an item of the open list, once Base UI's guard against a click right after opening has passed. */
async function choose(name: string) {
  await advance(500);
  fireEvent.click(screen.getByRole('option', { name }));
  await advance(0);
}

describe('filter bar suggestions', () => {
  it('lists the filters on a click on the empty bar', async () => {
    renderOrders();
    await clickBar();

    expect(screen.getByRole('option', { name: 'Status status:' })).toBeTruthy();
    expect(screen.getByRole('option', { name: 'Customer customer:' })).toBeTruthy();
    expect(screen.getByRole('option', { name: 'Total total:' })).toBeTruthy();
    expect(screen.getAllByRole('option')).toHaveLength(3);
  });

  it('lists Status\'s values once Status is chosen, and adds the chip a value makes', async () => {
    const visit = mockVisit();
    renderOrders();
    const input = await clickBar();

    await choose('Status status:');

    expect(input.value).toBe('status:');
    expect(document.activeElement).toBe(input);
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled']);

    await choose('Pending');

    expect(within(bar()).getByText('Status: Pending')).toBeTruthy();
    expect(input.value).toBe('');

    await advance(299);

    expect(visit).not.toHaveBeenCalled();

    await advance(1);

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visitData(visit)).toEqual({ filter: { status: 'pending' } });
  });

  it('offers status: for sta, and Enter on it puts status: in the input', async () => {
    const visit = mockVisit();
    renderOrders();
    const input = await clickBar();

    await type('sta');

    expect(screen.getByRole('option', { name: 'Status status:' })).toBeTruthy();
    expect(screen.getAllByRole('option')).toHaveLength(1);

    await press('ArrowDown');
    await press('Enter');

    expect(input.value).toBe('status:');
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled']);

    await advance(1000);

    expect(visit).not.toHaveBeenCalled();
  });

  it('lists the customers the server finds for customer:ann and filters by the chosen one\'s id', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValue(new Response(JSON.stringify({ options: [{ label: 'Ann Taylor', value: 12 }, { label: 'Annette Moss', value: 14 }] })));
    const visit = mockVisit();
    renderOrders();
    await clickBar();

    await type('customer:ann');
    await advance(500);

    const url = new URL(fetch.mock.calls[0]?.[0] as string, 'http://localhost');

    expect(url.pathname).toBe('/lathe/api/resources/orders/filters/customer/options');
    expect(url.searchParams.get('q')).toBe('ann');
    expect(url.searchParams.get('page')).toBe('1');
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ann Taylor', 'Annette Moss']);

    await choose('Ann Taylor');
    await advance(300);

    expect(within(bar()).getByText('Customer: Ann Taylor')).toBeTruthy();
    expect(visitData(visit)).toEqual({ filter: { customer: '12' } });
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('offers the other statuses after status:pending, and keeps pending with the one chosen', async () => {
    const visit = mockVisit();
    renderOrders();
    await clickBar();

    await type('status:pending,');

    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Paid', 'Shipped', 'Cancelled']);

    await choose('Paid');
    await advance(300);

    expect(within(bar()).getByText('Status: Pending, Paid')).toBeTruthy();
    expect(visitData(visit)).toEqual({ filter: { status: ['pending', 'paid'] } });
  });

  it('closes the list on Escape, and Enter with nothing highlighted still parses the text', async () => {
    const visit = mockVisit();
    renderOrders();
    await clickBar();

    expect(screen.getAllByRole('option')).toHaveLength(3);

    await press('Escape');
    await advance(500);

    expect(screen.queryAllByRole('option')).toHaveLength(0);

    await type('total:>1500');
    await press('Enter');
    await advance(300);

    expect(within(bar()).getByText('Total: ≥ 1,500')).toBeTruthy();
    expect(visitData(visit)).toEqual({ filter: { field_total_cents: { min: '150000' } } });
  });

  it('parses the text on Enter once typing has left the highlighted item behind', async () => {
    renderOrders();
    await clickBar();

    await type('sta');
    await press('ArrowDown');
    await type('status:zzz');
    await press('Enter');

    expect(within(bar()).getByText('Status has no option zzz')).toBeTruthy();
  });
});
