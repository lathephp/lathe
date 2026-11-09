import { router } from '@inertiajs/react';
import { emit, queryClient } from '@lathephp/core';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ResourceWidgets } from '../widgets/ResourceWidgets';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

beforeEach(() => {
  queryClient.clear();
  vi.useFakeTimers();
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  vi.useRealTimers();
});

function descriptor(uriKey: string, overrides: Partial<WidgetDescriptor> = {}): WidgetDescriptor {
  return {
    name: uriKey.replace(/^./, (first) => first.toUpperCase()),
    uriKey,
    component: 'value-widget',
    width: '1/3',
    columns: 4,
    height: 'fixed',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: false,
    onlyOnDetail: false,
    requestsValue: true,
    ranges: [],
    ...overrides,
  };
}

const urlOf = (input: RequestInfo | URL): string => (typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);

/** Answers each card's endpoint with the number the handler picks for its URL. */
function answer(number: (url: string) => number) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = urlOf(input);
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';

    return Promise.resolve(
      new Response(
        JSON.stringify({
          name: key,
          uriKey: key,
          component: 'value-widget',
          value: { value: number(url), previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false },
        }),
        { status: 200 },
      ),
    );
  });
}

function calls(fetch: ReturnType<typeof answer>): string[] {
  return fetch.mock.calls.map(([input]) => urlOf(input)).filter((url) => url.includes('/widgets/'));
}

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

async function pass(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

const sales = {
  uriKey: 'sales',
  name: 'Sales',
  description: null,
  icon: null,
  showRefreshButton: false,
  customizable: false,
  customized: false,
  ranges: [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
  ],
  range: 30,
};

function visitSales() {
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  renderPage('Dashboard', { dashboard: sales, widgets: [descriptor('orders'), descriptor('revenue')] }, { url: '/lathe/dashboards/sales' });
}

const ordersAt = (range: string) => `/lathe/api/dashboards/sales/widgets/orders?range=${range}`;
const revenueAt = (range: string) => `/lathe/api/dashboards/sales/widgets/revenue?range=${range}`;
const numberFor = (url: string) => (url.includes('orders') ? 1234 : 56) + (url.includes('range=7') ? 7 : 0);

describe('a dashboard return within 60 s', () => {
  it('shows every card from the cache and asks for no value', async () => {
    const fetch = answer(numberFor);
    visitSales();
    await pass(300);

    expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    expect(within(card('revenue')).getByText('56')).toBeTruthy();
    expect(calls(fetch)).toEqual([ordersAt('30'), revenueAt('30')]);

    cleanup();
    await pass(30_000);
    visitSales();

    expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    expect(within(card('revenue')).getByText('56')).toBeTruthy();
    await pass(1_000);
    expect(calls(fetch)).toHaveLength(2);
  });

  it('shows the kept values at once after 60 s and fetches each card once more behind them', async () => {
    const fetch = answer(numberFor);
    visitSales();
    await pass(300);

    cleanup();
    await pass(61_000);
    visitSales();

    expect(within(card('orders')).getByText('1,234')).toBeTruthy();
    expect(within(card('revenue')).getByText('56')).toBeTruthy();
    await pass(0);
    expect(calls(fetch)).toEqual([ordersAt('30'), revenueAt('30'), ordersAt('30'), revenueAt('30')]);
  });

  it('fetches every card again on Refresh', async () => {
    const fetch = answer(numberFor);
    visitSales();
    await pass(300);

    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));
    await pass(300);

    expect(calls(fetch)).toEqual([ordersAt('30'), revenueAt('30'), ordersAt('30'), revenueAt('30')]);
  });

  it('fetches and shows the 7-day values when the range changes', async () => {
    const fetch = answer(numberFor);
    visitSales();
    await pass(300);

    fireEvent.click(screen.getByRole('radio', { name: '7 days' }));
    await pass(300);

    expect(within(card('orders')).getByText('1,241')).toBeTruthy();
    expect(within(card('revenue')).getByText('63')).toBeTruthy();
    expect(calls(fetch)).toEqual([ordersAt('30'), revenueAt('30'), ordersAt('7'), revenueAt('7')]);
  });
});

describe('Resource cards', () => {
  it('serve a record page card from the cache within 60 s', async () => {
    const fetch = answer(() => 3);
    render(<ResourceWidgets widgets={[descriptor('orders')]} path="resources/orders" resourceId="12" />);
    await pass(300);
    expect(within(card('orders')).getByText('3')).toBeTruthy();

    cleanup();
    render(<ResourceWidgets widgets={[descriptor('orders')]} path="resources/orders" resourceId="12" />);

    expect(within(card('orders')).getByText('3')).toBeTruthy();
    await pass(300);
    expect(calls(fetch)).toEqual(['/lathe/api/resources/orders/widgets/orders?resourceId=12']);
  });

  it('serve a list card from the cache, keyed on the filters it last heard', async () => {
    const fetch = answer((url) => (url.includes('filter') ? 9 : 3));
    const list = () => render(<ResourceWidgets widgets={[descriptor('orders', { refreshWhenFiltersChange: true })]} path="resources/orders" />);
    list();
    await pass(300);

    act(() => {
      emit('filters:changed', { filters: { status: 'paid' } });
    });
    await pass(0);
    expect(within(card('orders')).getByText('9')).toBeTruthy();

    cleanup();
    list();

    expect(within(card('orders')).getByText('3')).toBeTruthy();
    await pass(300);
    expect(calls(fetch)).toEqual(['/lathe/api/resources/orders/widgets/orders', '/lathe/api/resources/orders/widgets/orders?filter%5Bstatus%5D=paid']);
  });
});
