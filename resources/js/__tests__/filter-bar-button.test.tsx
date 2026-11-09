import { router } from '@inertiajs/react';
import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { IndexFilter } from '../index/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const base = { remote: false, immediate: false, placement: 'panel', default: null };

const status: IndexFilter = {
  ...base,
  key: 'status',
  name: 'Status',
  component: 'multi-select-filter',
  options: [
    { label: 'Pending', value: 'pending' },
    { label: 'Paid', value: 'paid' },
  ],
  token: 'status',
  kind: 'multiple',
};
const customer: IndexFilter = { ...base, key: 'customer', name: 'Customer', component: 'select-filter', options: null, remote: true, token: 'customer', kind: 'relation' };
const placed: IndexFilter = { ...base, key: 'placed_at', name: 'Placed on', component: 'date-filter', options: null, token: 'placed', kind: 'date' };

function renderOrders(available: IndexFilter[], url = '/lathe/resources/orders') {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: true,
        softDeletes: false,
        abilities: { create: false },
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      filters: { available, applied: {} },
      staleFilters: [],
      count: null,
    },
    { url },
  );
}

describe('filter bar button', () => {
  it('opens the bar\'s list of filters on a click', async () => {
    renderOrders([status, customer, placed]);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    await screen.findByRole('combobox', { name: 'Search orders' });
    const options = await screen.findAllByRole('option');

    expect(options.map((option) => option.textContent)).toEqual(['Status status:', 'Customer customer:', 'Placed on placed:']);
  });

  it('is a native button, so Tab reaches it and Enter clicks it', () => {
    renderOrders([status, customer, placed]);
    const button = screen.getByRole('button', { name: 'Filter' });

    expect(button.tagName).toBe('BUTTON');
    expect(button.getAttribute('type')).toBe('button');
    expect(button.tabIndex).toBe(0);
  });

  it('opens the list again once the editor is loaded and the list was closed', async () => {
    renderOrders([status, customer, placed]);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    await screen.findByRole('listbox');
    fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'Escape' });
    await vi.waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));

    expect((await screen.findAllByRole('option')).length).toBe(3);
  });

  it('counts the active filter chips', () => {
    renderOrders([status, customer, placed], '/lathe/resources/orders?filter[status]=pending');

    expect(screen.getByRole('button', { name: 'Filter 1' })).toBeTruthy();
  });

  it('is absent when the Resource has no filters', () => {
    renderOrders([]);

    expect(screen.queryByRole('button', { name: /^Filter/ })).toBeNull();
  });

  it('adds the chip of a filter and value picked from its list', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders([status, customer, placed]);

    fireEvent.click(screen.getByRole('button', { name: 'Filter' }));
    await screen.findAllByRole('option');
    await new Promise((resolve) => setTimeout(resolve, 400));
    fireEvent.click(screen.getByRole('option', { name: 'Status status:' }));
    await screen.findByRole('option', { name: 'Pending' });
    await new Promise((resolve) => setTimeout(resolve, 400));
    fireEvent.click(screen.getByRole('option', { name: 'Pending' }));

    expect(await screen.findByText('Status: Pending')).toBeTruthy();
    await vi.waitFor(() => {
      expect(visit).toHaveBeenCalledTimes(1);
    });
    expect((visit.mock.calls[0]?.[1] as { data: unknown }).data).toEqual({ filter: { status: 'pending' } });
  });
});
