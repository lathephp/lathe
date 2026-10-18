import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

const pointerCapture = Object.getOwnPropertyDescriptor(HTMLElement.prototype, 'setPointerCapture');
const elementFromPoint = Object.getOwnPropertyDescriptor(document, 'elementFromPoint');

beforeEach(() => {
  __panel.toasts.set([]);
});

afterEach(() => {
  vi.restoreAllMocks();

  for (const [target, key, found] of [
    [HTMLElement.prototype, 'setPointerCapture', pointerCapture],
    [document, 'elementFromPoint', elementFromPoint],
  ] as const) {
    if (found === undefined) {
      Reflect.deleteProperty(target, key);
    } else {
      Object.defineProperty(target, key, found);
    }
  }
});

const names: Record<string, string> = { revenue: 'Revenue', orders: 'Orders', 'orders-by-status': 'Orders by status', 'paid-orders': 'Paid orders' };

function descriptor(uriKey: string): WidgetDescriptor {
  return {
    name: names[uriKey] ?? uriKey,
    uriKey,
    component: 'value-widget',
    width: uriKey === 'orders-by-status' ? '1/3' : '1/4',
    columns: uriKey === 'orders-by-status' ? 4 : 3,
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
  };
}

const value = (uriKey: string, number: number) => ({
  name: names[uriKey],
  uriKey,
  component: 'value-widget',
  value: { value: number, previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false },
});

// The reload stands in for Inertia's: it finishes at once.
function renderDashboard(shown: string[], hidden: string[] = []) {
  const reload = vi.spyOn(router, 'reload').mockImplementation((options) => {
    options?.onFinish?.({} as never);
  });
  renderPage('Dashboard', {
    dashboard: { uriKey: 'main', name: 'Sales', description: null, icon: null, showRefreshButton: false, customizable: true, customized: false, ranges: [], range: null },
    widgets: shown.map(descriptor),
    hiddenWidgets: hidden.map(descriptor),
    ...Object.fromEntries(shown.map((key, at) => [`widgetValue:${key}`, value(key, at + 10)])),
  });
  reload.mockClear();

  return reload;
}

function responding(status: number, body: unknown = { layout: [] }) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response(status === 204 ? null : JSON.stringify(body), { status })));
}

function calls(fetch: { mock: { calls: unknown[][] } }) {
  return fetch.mock.calls.map((call) => {
    const init = call[1] as RequestInit | undefined;

    return { url: call[0], method: init?.method, body: typeof init?.body === 'string' ? (JSON.parse(init.body) as unknown) : undefined };
  });
}

const order = () => [...document.querySelectorAll<HTMLElement>('[data-lathe-widget]')].map((card) => card.dataset.latheWidget);

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

async function customize() {
  fireEvent.click(screen.getByRole('button', { name: 'Customize' }));
  await screen.findByText('Editing layout');
}

describe('saving a customized layout', () => {
  it('drags, resizes and hides cards, then saves the whole list and reloads', async () => {
    const reload = renderDashboard(['orders', 'revenue', 'orders-by-status', 'paid-orders']);
    const fetch = responding(200);
    await customize();

    // Orders goes after Revenue: the pointer lands on Revenue's card.
    Object.defineProperty(HTMLElement.prototype, 'setPointerCapture', { configurable: true, value: vi.fn() });
    Object.defineProperty(document, 'elementFromPoint', { configurable: true, value: () => card('revenue') });
    const handle = screen.getByRole('button', { name: 'Move Orders' });
    fireEvent.pointerDown(handle, { pointerId: 1, button: 0 });
    fireEvent.pointerMove(handle, { pointerId: 1 });
    fireEvent.pointerUp(handle, { pointerId: 1 });

    expect(order()).toEqual(['revenue', 'orders', 'orders-by-status', 'paid-orders']);

    fireEvent.click(within(card('orders-by-status')).getByRole('button', { name: 'Menu for Orders by status' }));
    fireEvent.click(screen.getByRole('menuitemradio', { name: 'Large' }));
    expect(card('orders-by-status').className).toContain('md:col-span-6');

    fireEvent.click(within(card('paid-orders')).getByRole('button', { name: 'Menu for Paid orders' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Hide from this dashboard' }));
    expect(order()).toEqual(['revenue', 'orders', 'orders-by-status']);

    fireEvent.click(screen.getByRole('button', { name: 'Save layout' }));

    await waitFor(() => {
      expect(screen.queryByText('Editing layout')).toBeNull();
    });
    expect(calls(fetch)).toEqual([
      {
        url: '/lathe/api/dashboards/main/layout',
        method: 'PUT',
        body: {
          widgets: [
            { uriKey: 'revenue', width: '1/4', hidden: false },
            { uriKey: 'orders', width: '1/4', hidden: false },
            { uriKey: 'orders-by-status', width: '1/2', hidden: false },
            { uriKey: 'paid-orders', width: '1/4', hidden: true },
          ],
        },
      },
    ]);
    expect(reload).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Customize' })).toBeTruthy();
  });

  it('saves a card added back from the panel last and shown', async () => {
    renderDashboard(['revenue', 'orders'], ['paid-orders']);
    const fetch = responding(200);
    await customize();

    fireEvent.click(within(screen.getByRole('region', { name: 'Add a widget' })).getByRole('button', { name: 'Add Paid orders' }));
    fireEvent.click(screen.getByRole('button', { name: 'Save layout' }));

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(1);
    });
    expect(calls(fetch)[0]?.body).toEqual({
      widgets: [
        { uriKey: 'revenue', width: '1/4', hidden: false },
        { uriKey: 'orders', width: '1/4', hidden: false },
        { uriKey: 'paid-orders', width: '1/4', hidden: false },
      ],
    });
  });

  it('resets to the code layout and reloads', async () => {
    const reload = renderDashboard(['orders', 'revenue']);
    const fetch = responding(204);
    await customize();

    fireEvent.click(screen.getByRole('button', { name: 'Reset to default' }));

    await waitFor(() => {
      expect(screen.queryByText('Editing layout')).toBeNull();
    });
    expect(calls(fetch)).toEqual([{ url: '/lathe/api/dashboards/main/layout', method: 'DELETE', body: undefined }]);
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('cancels without a request and puts the order back', async () => {
    const reload = renderDashboard(['orders', 'revenue']);
    const fetch = responding(200);
    await customize();

    fireEvent.keyDown(screen.getByRole('button', { name: 'Move Orders' }), { key: ' ' });
    fireEvent.keyDown(screen.getByRole('button', { name: 'Move Orders' }), { key: 'ArrowRight' });
    expect(order()).toEqual(['revenue', 'orders']);

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(screen.queryByText('Editing layout')).toBeNull();
    expect(order()).toEqual(['orders', 'revenue']);
    expect(fetch).not.toHaveBeenCalled();
    expect(reload).not.toHaveBeenCalled();
  });

  it('stays in edit mode with an error toast when the save fails', async () => {
    const reload = renderDashboard(['orders', 'revenue']);
    responding(500, { message: 'Server Error' });
    await customize();

    fireEvent.click(screen.getByRole('button', { name: 'Save layout' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['The layout could not be saved.']);
    });
    expect(screen.getByText('Editing layout')).toBeTruthy();
    expect(reload).not.toHaveBeenCalled();
  });
});
