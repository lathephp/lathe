import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import { fakeInertiaServer, renderPage } from './support';

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const columns = ['Number', 'Customer'].map((label) => ({ key: label.toLowerCase(), label, component: 'text-field', sortable: false, sortKey: label.toLowerCase() }));

function orders(count: number) {
  return {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: true, abilities: { create: false }, definition },
    table: {
      columns,
      rows: Array.from({ length: count }, (_, index) => {
        const id = index + 1;

        return {
          id,
          title: `ORD-${String(id)}`,
          cells: Object.fromEntries(columns.map((column) => [column.key, { value: `${column.label} ${String(id)}`, display: `${column.label} ${String(id)}` }])),
          abilities: { view: true, update: true },
        };
      }),
      rowKey: 'id',
      sortKeys: [],
    },
    state: { page: 1, perPage: 25, hasMorePages: false },
  };
}

beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
});

/** Waits for the cursor's code, then presses a key where the browser's focus is. */
async function press(key: string, init: Partial<KeyboardEventInit> = {}) {
  await waitFor(() => {
    expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
  });
  fireEvent.keyDown(document.activeElement ?? document.body, { key, ...init });
}

function focusCell(text: string) {
  act(() => {
    screen.getByText(text).closest('td')?.focus();
  });
}

const bar = () => screen.queryByText(/selected/)?.textContent ?? null;

describe('selecting rows from the grid', () => {
  it('toggles the cursor row with X and extends the selection with Shift+Down', async () => {
    renderPage('Resources/Index', orders(6), { url: '/lathe/resources/orders' });
    focusCell('Number 1');

    await press('x');
    await press('ArrowDown', { shiftKey: true });
    await press('ArrowDown', { shiftKey: true });

    expect(bar()).toBe('3 selected');
    expect(document.activeElement?.textContent).toBe('Number 3');
  });

  it('selects the range from the last ticked checkbox to a Shift+clicked one', async () => {
    renderPage('Resources/Index', orders(6), { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
    });

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select ORD-1' }));
    fireEvent.click(screen.getByRole('checkbox', { name: 'Select ORD-5' }), { shiftKey: true });

    expect(bar()).toBe('5 selected');
  });

  it('clears the selection with Escape on the grid', async () => {
    renderPage('Resources/Index', orders(6), { url: '/lathe/resources/orders' });
    focusCell('Number 2');
    await press('X');
    expect(bar()).toBe('1 selected');

    await press('Escape');

    expect(bar()).toBeNull();
  });

  it('types x into the search box without selecting a row', async () => {
    renderPage('Resources/Index', orders(3), { url: '/lathe/resources/orders' });
    const search = screen.getByRole<HTMLInputElement>('searchbox');
    act(() => {
      search.focus();
    });

    await press('x');
    fireEvent.change(search, { target: { value: 'x' } });

    expect(search.value).toBe('x');
    expect(bar()).toBeNull();
  });
});
