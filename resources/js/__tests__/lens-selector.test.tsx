import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

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

describe('lens tabs', () => {
  it('puts each lens in the tab band as a link, none current on the Resource itself', () => {
    renderOrders([pending]);

    const band = screen.getByRole('navigation', { name: 'Tabs' });
    const link = within(band).getByRole('link', { name: 'Pending Orders' });

    expect(link.getAttribute('href')).toBe('/lathe/resources/orders/lens/pending-orders');
    expect(link.getAttribute('aria-current')).toBeNull();
  });

  it('marks the open lens as current and heads the page with its name and a back link', () => {
    renderOrders([pending], pending);

    expect(within(screen.getByRole('navigation', { name: 'Tabs' })).getByRole('link', { name: 'Pending Orders' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Pending Orders');
    const heading = screen.getByRole('heading', { level: 1 }).parentElement as HTMLElement;

    expect(within(heading).getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
  });

  it('draws no lens tab and no view switcher when the Resource has no lenses', () => {
    renderOrders([]);

    expect(screen.queryByRole('navigation', { name: 'Tabs' })).toBeNull();
    expect(screen.queryByRole('combobox', { name: 'View' })).toBeNull();
  });
});
