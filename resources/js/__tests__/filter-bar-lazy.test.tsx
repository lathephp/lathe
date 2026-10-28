import { act, fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

const loads = vi.hoisted(() => ({ count: 0 }));

// Counts the editor module's evaluations: it runs only when something imports it.
vi.mock('../index/FilterBarEditor', async (original) => {
  loads.count += 1;

  return original();
});

function renderOrders() {
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
      filters: { available: [], applied: {} },
      staleFilters: [],
      count: null,
    },
    { url: '/lathe/resources/orders?q=ann' },
  );
}

describe('filter bar editor', () => {
  it('loads on pointer enter, and takes over on focus with the typed text and the focus', async () => {
    renderOrders();
    const plain = screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' });

    expect(plain.value).toBe('ann');
    await vi.dynamicImportSettled();
    expect(loads.count).toBe(0);

    fireEvent.pointerEnter(plain);
    await vi.waitFor(() => {
      expect(loads.count).toBe(1);
    });
    expect(screen.queryByRole('combobox')).toBeNull();

    act(() => {
      plain.focus();
    });
    fireEvent.change(plain, { target: { value: 'ann t' } });
    const input = await screen.findByRole<HTMLInputElement>('combobox', { name: 'Search orders' });

    expect(document.activeElement).toBe(input);
    expect(input.value).toBe('ann t');
  });
});
