import { emit } from '@lathephp/core';
import { act, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';
import { choose } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function descriptor(overrides: Partial<WidgetDescriptor> = {}): WidgetDescriptor {
  return {
    name: 'Orders',
    uriKey: 'orders',
    component: 'value-widget',
    width: '1/3',
    columns: 4,
    height: 'fixed',
    help: null,
    helpWidth: 250,
    refreshEvery: 0,
    refreshWhenActionsRun: false,
    refreshWhenFiltersChange: true,
    onlyOnDetail: false,
    requestsValue: true,
    ranges: [],
    ...overrides,
  };
}

const value = {
  value: 12,
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

/** Answers every value request and returns the URLs asked for, decoded. */
function answer() {
  const fetch = vi
    .spyOn(globalThis, 'fetch')
    .mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ name: 'orders', uriKey: 'orders', component: 'value-widget', value }), { status: 200 })));

  return () => fetch.mock.calls.map(([input]) => decodeURIComponent(typeof input === 'string' ? input : input instanceof URL ? input.href : input.url));
}

const grid = (widget: WidgetDescriptor) => <WidgetGrid widgets={[widget]} valueUrl="/lathe/api/resources/orders/widgets" />;

describe('a card on a filtered Resource page', () => {
  it('sends the new filters in the refetch after filters:changed', async () => {
    const urls = answer();
    render(grid(descriptor()));
    await screen.findByText('12');

    act(() => {
      emit('filters:changed', { resource: 'orders', filters: { status: 'pending', tags: ['a', 'b'], total: { min: '5', max: '9' } } });
    });

    await waitFor(() => {
      expect(urls()).toHaveLength(2);
    });
    expect(urls()[1]).toBe('/lathe/api/resources/orders/widgets/orders?filter[status]=pending&filter[tags][]=a&filter[tags][]=b&filter[total][min]=5&filter[total][max]=9');
  });

  it('keeps the filters on a later range change and drops them when they clear', async () => {
    const urls = answer();
    const ranges = [
      { value: 30, label: '30 Days' },
      { value: 60, label: '60 Days' },
    ];
    render(grid(descriptor({ ranges })));
    await screen.findByText('12');

    act(() => {
      emit('filters:changed', { resource: 'orders', filters: { status: 'paid' } });
    });
    await waitFor(() => {
      expect(urls()).toHaveLength(2);
    });
    await choose(within(document.body).getByRole('combobox', { name: 'Range' }), '60 Days');
    await waitFor(() => {
      expect(urls()).toHaveLength(3);
    });
    act(() => {
      emit('filters:changed', { resource: 'orders', filters: {} });
    });
    await waitFor(() => {
      expect(urls()).toHaveLength(4);
    });

    expect(urls().slice(1)).toEqual([
      '/lathe/api/resources/orders/widgets/orders?range=30&filter[status]=paid',
      '/lathe/api/resources/orders/widgets/orders?range=60&filter[status]=paid',
      '/lathe/api/resources/orders/widgets/orders?range=60',
    ]);
  });
});

describe('a value card', () => {
  it('asks for its default range first', async () => {
    const urls = answer();
    const ranges = [
      { value: 30, label: '30 Days' },
      { value: 60, label: '60 Days' },
    ];
    render(grid(descriptor({ ranges, defaultRange: 60 })));
    await screen.findByText('12');

    expect(urls()).toEqual(['/lathe/api/resources/orders/widgets/orders?range=60']);
    expect(within(document.body).getByRole('combobox', { name: 'Range' }).textContent).toBe('60 Days');
  });

  it('draws the icon the descriptor names beside the number', async () => {
    answer();
    render(grid(descriptor({ icon: 'shopping-cart' })));
    await screen.findByText('12');

    await waitFor(() => {
      expect(document.querySelector('[data-lathe-widget="orders"] svg.lucide-shopping-cart')).not.toBeNull();
    });
  });

  it('draws no icon when the descriptor names none', async () => {
    answer();
    render(grid(descriptor({ icon: null })));
    await screen.findByText('12');

    expect(document.querySelector('[data-lathe-widget="orders"] svg')).toBeNull();
  });
});
