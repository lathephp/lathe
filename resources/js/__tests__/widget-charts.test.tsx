import { render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';

// Set when the chart module is first imported, which only a dynamic import does.
const charts = vi.hoisted(() => ({ loaded: false }));

vi.mock('../widgets/charts', async (importOriginal) => {
  charts.loaded = true;

  return importOriginal();
});

afterEach(() => {
  vi.restoreAllMocks();
});

function descriptor(uriKey: string, component: string): WidgetDescriptor {
  return {
    name: uriKey === 'orders-by-status' ? 'Orders by Status' : 'Total Orders',
    uriKey,
    component,
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
  };
}

const bars = {
  bars: [
    { label: 'Paid', value: 42, color: 'var(--chart-1)' },
    { label: 'Refunded', value: 7, color: 'var(--chart-2)' },
  ],
};

const total = {
  value: 49,
  previous: null,
  change: null,
  previousLabel: null,
  prefix: null,
  suffix: null,
  suffixInflection: true,
  format: null,
  allowZeroResult: false,
  copyable: false,
};

function answer() {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';
    const value = key === 'orders-by-status' ? bars : total;

    return Promise.resolve(new Response(JSON.stringify({ name: key, uriKey: key, component: '', value }), { status: 200 }));
  });
}

describe('a bar card', () => {
  it('loads no chart code for a page without chart cards', async () => {
    answer();
    render(<WidgetGrid widgets={[descriptor('total-orders', 'value-widget')]} valueUrl="/lathe-api/dashboards/main/widgets" />);

    await screen.findByText('49');

    expect(charts.loaded).toBe(false);
  });

  it('names the chart after the widget and lists each bar in a hidden table', async () => {
    answer();
    render(<WidgetGrid widgets={[descriptor('orders-by-status', 'bar-widget')]} valueUrl="/lathe-api/dashboards/main/widgets" />);

    const chart = await screen.findByLabelText('Orders by Status');
    const table = within(chart).getByRole('table');

    expect(table.closest('.sr-only')).not.toBeNull();
    expect([...table.querySelectorAll('tbody tr')].map((row) => [...row.children].map((cell) => cell.textContent))).toEqual([
      ['Paid', '42'],
      ['Refunded', '7'],
    ]);
    await waitFor(() => {
      expect(charts.loaded).toBe(true);
    });
  });
});
