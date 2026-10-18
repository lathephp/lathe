import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function descriptor(uriKey: string, overrides: Partial<WidgetDescriptor> = {}): WidgetDescriptor {
  return {
    name: uriKey === 'orders-per-day' ? 'Orders per day' : 'Orders',
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
    csv: false,
    ...overrides,
  };
}

const value = (uriKey: string, number: number, link?: string) => ({
  name: uriKey,
  uriKey,
  component: 'value-widget',
  value: { value: number, previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false },
  ...(link === undefined ? {} : { link }),
});

const dashboard = {
  uriKey: 'main',
  name: 'Sales',
  description: null,
  icon: null,
  showRefreshButton: false,
  customizable: true,
  ranges: [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 365, label: '12 months' },
  ],
  range: 30,
};

function renderDashboard(widgets: WidgetDescriptor[], values: Record<string, ReturnType<typeof value>>) {
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  renderPage('Dashboard', { dashboard, widgets, ...Object.fromEntries(Object.entries(values).map(([key, found]) => [`widgetValue:${key}`, found])) });
}

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

async function openMenu(uriKey: string, name: string): Promise<HTMLElement> {
  await waitFor(() => {
    expect(within(card(uriKey)).queryByRole('status')).toBeNull();
  });
  fireEvent.click(within(card(uriKey)).getByRole('button', { name: `Menu for ${name}` }));

  return within(card(uriKey)).getByRole('menu');
}

describe('a dashboard widget menu', () => {
  it('offers Refresh, Open records and Download CSV on a chart that reads one resource', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': value('orders-per-day', 12, '/lathe/resources/orders') });

    const menu = await openMenu('orders-per-day', 'Orders per day');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Refresh', 'Open records', 'Download CSV']);
    expect(within(menu).getByRole('menuitem', { name: 'Open records' }).getAttribute('href')).toBe('/lathe/resources/orders');

    const download = within(menu).getByRole('menuitem', { name: 'Download CSV' });
    expect(download.getAttribute('href')).toBe('/lathe/api/dashboards/main/widgets/orders-per-day/csv?range=30');
    expect(download.hasAttribute('download')).toBe(true);
  });

  it('has no Download CSV on a value card and no Open records without a link', async () => {
    renderDashboard([descriptor('orders')], { orders: value('orders', 12) });

    const menu = await openMenu('orders', 'Orders');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Refresh']);
  });

  it('refetches the card from Refresh and closes', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(value('orders', 99)), { status: 200 }));
    renderDashboard([descriptor('orders')], { orders: value('orders', 12) });

    fireEvent.click(within(await openMenu('orders', 'Orders')).getByRole('menuitem', { name: 'Refresh' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('99')).toBeTruthy();
    });
    expect(fetch.mock.calls.map(([input]) => (input instanceof Request ? input.url : input.toString()))).toEqual(['/lathe/api/dashboards/main/widgets/orders?range=30']);
    expect(screen.queryByRole('menu')).toBeNull();
  });

  it('takes focus into the menu, moves it with the arrow keys and closes on Escape back on its button', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': value('orders-per-day', 12, '/lathe/resources/orders') });

    const menu = await openMenu('orders-per-day', 'Orders per day');
    const [refresh, open] = within(menu).getAllByRole('menuitem');

    expect(document.activeElement).toBe(refresh);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'ArrowDown' });
    expect(document.activeElement).toBe(open);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'ArrowUp' });
    expect(document.activeElement).toBe(refresh);

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    expect(screen.queryByRole('menu')).toBeNull();
    expect(document.activeElement).toBe(within(card('orders-per-day')).getByRole('button', { name: 'Menu for Orders per day' }));
  });

  it('closes on Escape while its button has focus', async () => {
    renderDashboard([descriptor('orders')], { orders: value('orders', 12) });
    await openMenu('orders', 'Orders');
    const button = within(card('orders')).getByRole('button', { name: 'Menu for Orders' });
    button.focus();

    fireEvent.keyDown(button, { key: 'Escape' });

    expect(screen.queryByRole('menu')).toBeNull();
  });
});
