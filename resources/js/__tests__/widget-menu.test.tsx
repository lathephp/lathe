import { router } from '@inertiajs/react';
import { queryClient } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

beforeEach(() => {
  queryClient.clear();
});

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

/** Renders the dashboard with each card's endpoint answering its values in turn, then the last one. */
function renderDashboard(widgets: WidgetDescriptor[], values: Record<string, ReturnType<typeof value>[]>) {
  const asked: Record<string, number> = {};
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const key = /widgets\/([^/?]+)/.exec(input instanceof Request ? input.url : input.toString())?.[1] ?? '';
    const list = values[key] ?? [];
    const at = asked[key] ?? 0;
    asked[key] = at + 1;

    return Promise.resolve(new Response(JSON.stringify(list[Math.min(at, list.length - 1)]), { status: 200 }));
  });
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  renderPage('Dashboard', { dashboard, widgets });

  return fetch;
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
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });

    const menu = await openMenu('orders-per-day', 'Orders per day');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Refresh', 'Open records', 'Download CSV']);
    expect(within(menu).getByRole('menuitem', { name: 'Open records' }).getAttribute('href')).toBe('/lathe/resources/orders');

    const download = within(menu).getByRole('menuitem', { name: 'Download CSV' });
    expect(download.getAttribute('href')).toBe('/lathe/api/dashboards/main/widgets/orders-per-day/csv?range=30');
    expect(download.hasAttribute('download')).toBe(true);
  });

  it('has no Download CSV on a value card and no Open records without a link', async () => {
    renderDashboard([descriptor('orders')], { orders: [value('orders', 12)] });

    const menu = await openMenu('orders', 'Orders');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Refresh']);
  });

  it('refetches the card from Refresh and closes', async () => {
    const fetch = renderDashboard([descriptor('orders')], { orders: [value('orders', 12), value('orders', 99)] });

    fireEvent.click(within(await openMenu('orders', 'Orders')).getByRole('menuitem', { name: 'Refresh' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('99')).toBeTruthy();
    });
    // The mount's read, then Refresh's.
    expect(fetch.mock.calls.map(([input]) => (input instanceof Request ? input.url : input.toString()))).toEqual([
      '/lathe/api/dashboards/main/widgets/orders?range=30',
      '/lathe/api/dashboards/main/widgets/orders?range=30',
    ]);
    expect(screen.queryByRole('menu')).toBeNull();
  });

  it('takes focus into the menu, moves it with the arrow keys and closes on Escape back on its button', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });

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

  it('draws with the kit menu classes and no @lathephp/ui import', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });

    const menu = await openMenu('orders-per-day', 'Orders per day');

    expect(menu.classList.contains('cn-dropdown-menu-content')).toBe(true);
    expect(menu.getAttribute('data-open')).toBe('');
    expect(menu.getAttribute('data-side')).toBe('bottom');
    within(menu)
      .getAllByRole('menuitem')
      .forEach((item) => {
        expect(item.classList.contains('cn-dropdown-menu-item')).toBe(true);
        expect(item.getAttribute('data-variant')).toBe('default');
      });
    expect(readFileSync('resources/js/widgets/WidgetMenu.tsx', 'utf8')).not.toContain('@lathephp/ui');
    expect(readFileSync('resources/js/lib/menuKeys.ts', 'utf8')).not.toContain('@lathephp/ui');
  });

  it('wraps the arrow keys and goes to the ends with Home and End', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });
    const [refresh, , download] = within(await openMenu('orders-per-day', 'Orders per day')).getAllByRole('menuitem');

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'ArrowUp' });
    expect(document.activeElement).toBe(download);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'ArrowDown' });
    expect(document.activeElement).toBe(refresh);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'End' });
    expect(document.activeElement).toBe(download);
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Home' });
    expect(document.activeElement).toBe(refresh);
  });

  it('focuses the item a typed letter starts and keeps the letter from the document', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });
    const [, , download] = within(await openMenu('orders-per-day', 'Orders per day')).getAllByRole('menuitem');
    const reached = vi.fn();
    document.addEventListener('keydown', reached);

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'd' });
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'c' });
    document.removeEventListener('keydown', reached);

    expect(document.activeElement).toBe(download);
    expect(reached).not.toHaveBeenCalled();
  });

  // The kit's items light up on focus, and a kit menu focuses the item under the pointer.
  it('focuses the item the pointer moves onto, so it lights up as a kit item does', async () => {
    renderDashboard([descriptor('orders-per-day', { csv: true })], { 'orders-per-day': [value('orders-per-day', 12, '/lathe/resources/orders')] });
    const [, open] = within(await openMenu('orders-per-day', 'Orders per day')).getAllByRole('menuitem');

    fireEvent.pointerMove(open as HTMLElement);

    expect(document.activeElement).toBe(open);
  });

  it('closes on a press outside it', async () => {
    renderDashboard([descriptor('orders')], { orders: [value('orders', 12)] });
    await openMenu('orders', 'Orders');

    fireEvent.pointerDown(document.body);

    expect(screen.queryByRole('menu')).toBeNull();
  });

  it('closes on Escape while its button has focus', async () => {
    renderDashboard([descriptor('orders')], { orders: [value('orders', 12)] });
    await openMenu('orders', 'Orders');
    const button = within(card('orders')).getByRole('button', { name: 'Menu for Orders' });
    button.focus();

    fireEvent.keyDown(button, { key: 'Escape' });

    expect(screen.queryByRole('menu')).toBeNull();
  });
});
