import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

afterEach(() => {
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

function valueOf(uriKey: string, value: number) {
  return {
    name: uriKey,
    uriKey,
    component: 'value-widget',
    value: {
      value,
      previous: null,
      change: null,
      previousLabel: null,
      prefix: null,
      suffix: null,
      suffixInflection: true,
      format: null,
      allowZeroResult: false,
      copyable: false,
    },
  };
}

const dashboard = {
  uriKey: 'main',
  name: 'Sales',
  description: null,
  icon: null,
  showRefreshButton: false,
  ranges: [
    { value: 7, label: '7 days' },
    { value: 30, label: '30 days' },
    { value: 365, label: '12 months' },
  ],
  range: 30,
};

/** Answers every value request with the number the handler picks; the list endpoint answers `list`. */
const urlOf = (input: RequestInfo | URL): string => (typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);

function answer(number: (url: string) => number | Error, list: WidgetDescriptor[] = []) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = urlOf(input);

    if (url.endsWith('/widgets') || url.includes('/widgets?')) {
      return Promise.resolve(new Response(JSON.stringify({ widgets: list }), { status: 200 }));
    }

    const result = number(url);
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';

    return Promise.resolve(
      result instanceof Error
        ? new Response(JSON.stringify({ message: result.message }), { status: 500 })
        : new Response(JSON.stringify(valueOf(key, result)), { status: 200 }),
    );
  });
}

function calls(fetch: ReturnType<typeof answer>, part = '/widgets/'): string[] {
  return fetch.mock.calls.map(([input]) => urlOf(input)).filter((url) => url.includes(part));
}

function card(uriKey: string): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-lathe-widget="${uriKey}"]`);

  if (found === null) {
    throw new Error(`No card ${uriKey}.`);
  }

  return found;
}

function renderDashboard(widgets: WidgetDescriptor[], values: Record<string, number>, page: Record<string, unknown> = {}) {
  const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);

  renderPage(
    'Dashboard',
    {
      dashboard,
      widgets,
      ...Object.fromEntries(Object.entries(values).map(([key, value]) => [`widgetValue:${key}`, valueOf(key, value)])),
    },
    { page },
  );

  return { reload };
}

describe('the dashboard range', () => {
  const ranges = [
    { value: 30, label: '30 Days' },
    { value: 60, label: '60 Days' },
  ];

  it('refetches every card with the picked range and keeps it in the URL', async () => {
    const fetch = answer((url) => (url.includes('range=365') ? 365 : 30));
    const { reload } = renderDashboard([descriptor('orders', { ranges }), descriptor('revenue')], { orders: 1, revenue: 2 });

    fireEvent.click(screen.getByRole('radio', { name: '12 months' }));

    await waitFor(() => {
      expect(within(card('orders')).getByText('365')).toBeTruthy();
      expect(within(card('revenue')).getByText('365')).toBeTruthy();
    });
    expect(calls(fetch).sort()).toEqual(['/lathe/api/dashboards/main/widgets/orders?range=365', '/lathe/api/dashboards/main/widgets/revenue?range=365']);
    expect(window.location.pathname + window.location.search).toBe('/lathe?range=365');
    expect(screen.getByRole('radio', { name: '12 months' }).getAttribute('aria-checked')).toBe('true');
    expect(reload).not.toHaveBeenCalled();
  });
});
