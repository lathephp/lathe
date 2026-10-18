import { router } from '@inertiajs/react';
import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { choose, optionLabels, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const pending = { uriKey: 'pending-orders', name: 'Pending Orders' };

function renderOrders(lenses: { uriKey: string; name: string }[], lens: { uriKey: string; name: string } | null = null) {
  const props = {
    resource: {
      uriKey: 'orders',
      label: 'Orders',
      singularLabel: 'Order',
      perPageOptions: [25],
      searchable: false,
      abilities: { create: false },
      definition: { debounce: 0.5 },
    },
    table: { columns: [], sortKeys: [], rows: [{ id: 1, title: 'ORD-1', cells: {}, abilities: { view: true } }], rowKey: 'id' },
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    count: null,
    lens,
    lenses,
  };
  const url = lens === null ? '/lathe/resources/orders' : `/lathe/resources/orders/lens/${lens.uriKey}`;

  return renderPage('Resources/Index', props, { url });
}

describe('lens selector', () => {
  it('lists the Resource and its lenses and marks the Resource as current', async () => {
    renderOrders([pending]);

    const select = screen.getByRole('combobox', { name: 'View' });

    expect(select.textContent).toBe('Orders');
    expect(await optionLabels(select)).toEqual(['Orders', 'Pending Orders']);
  });

  it('marks the open lens as current and heads the page with its name and a back link', () => {
    renderOrders([pending], pending);

    expect(screen.getByRole('combobox', { name: 'View' }).textContent).toBe('Pending Orders');
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Pending Orders');
    const heading = screen.getByRole('heading', { level: 1 }).parentElement as HTMLElement;

    expect(within(heading).getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
  });

  it('visits the lens URL and back to the Resource URL', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    renderOrders([pending], pending);
    await choose(screen.getByRole('combobox', { name: 'View' }), 'Orders');

    expect(visit).toHaveBeenLastCalledWith('/lathe/resources/orders');

    renderOrders([pending]);
    await choose(screen.getAllByRole('combobox', { name: 'View' })[1] as HTMLElement, 'Pending Orders');

    expect(visit).toHaveBeenLastCalledWith('/lathe/resources/orders/lens/pending-orders');
  });

  it('shows no switcher when the Resource has no lenses', () => {
    renderOrders([]);

    expect(screen.queryByRole('combobox', { name: 'View' })).toBeNull();
  });
});
