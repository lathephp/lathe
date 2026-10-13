import { router } from '@inertiajs/react';
import { fireEvent, screen, within } from '@testing-library/react';
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

describe('lens selector', () => {
  it('lists the Resource and its lenses and marks the Resource as current', () => {
    renderOrders([pending]);

    const select = screen.getByRole<HTMLSelectElement>('combobox', { name: 'View' });

    expect(Array.from(select.options).map((option) => option.text)).toEqual(['Orders', 'Pending Orders']);
    expect(select.value).toBe('');
  });

  it('marks the open lens as current and heads the page with its name and a back link', () => {
    renderOrders([pending], pending);

    expect(screen.getByRole<HTMLSelectElement>('combobox', { name: 'View' }).value).toBe('pending-orders');
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Pending Orders');
    const heading = screen.getByRole('heading', { level: 1 }).parentElement as HTMLElement;

    expect(within(heading).getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
  });

  it('visits the lens URL and back to the Resource URL', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    renderOrders([pending]);
    fireEvent.change(screen.getByRole('combobox', { name: 'View' }), { target: { value: 'pending-orders' } });

    expect(visit).toHaveBeenLastCalledWith('/lathe/resources/orders/lens/pending-orders');

    fireEvent.change(screen.getByRole('combobox', { name: 'View' }), { target: { value: '' } });

    expect(visit).toHaveBeenLastCalledWith('/lathe/resources/orders');
  });

  it('shows no switcher when the Resource has no lenses', () => {
    renderOrders([]);

    expect(screen.queryByRole('combobox', { name: 'View' })).toBeNull();
  });
});
