import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const columns = ['Number', 'Customer', 'Status', 'Total', 'Placed'].map((label) => ({ key: label.toLowerCase(), label, component: 'text-field', sortable: false, sortKey: label.toLowerCase() }));
const statuses = ['pending', 'pending', 'paid', 'paid', 'shipped'];

function orders(count: number, grouped = false) {
  return {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], abilities: { create: false }, definition },
    table: {
      columns,
      rows: Array.from({ length: count }, (_, index) => {
        const id = index + 1;
        const status = statuses[index % statuses.length] ?? 'pending';

        return {
          id,
          title: `ORD-${String(id)}`,
          cells: Object.fromEntries(columns.map((column) => [column.key, { value: `${column.label} ${String(id)}`, display: `${column.label} ${String(id)}` }])),
          abilities: { view: true, update: true },
          ...(grouped ? { group: { key: status, title: `Status: ${status}`, description: null } } : {}),
        };
      }).sort((a, b) => (grouped ? (a.group?.key ?? '').localeCompare(b.group?.key ?? '') : 0)),
      rowKey: 'id',
      sortKeys: [],
    },
    state: { page: 1, perPage: 25, hasMorePages: false },
    ...(grouped ? { groups: { available: [{ id: 'status', label: 'Status' }], active: { id: 'status', direction: 'asc', collapsible: true } } } : {}),
  };
}

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

/** Waits for the cursor's code, then presses a key where the browser's focus is. */
async function press(key: string, init: Partial<KeyboardEventInit> = {}) {
  await waitFor(() => {
    expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
  });
  fireEvent.keyDown(document.activeElement ?? document.body, { key, ...init });
}

/** The one element of the grid in the tab order, which must be a cell. */
function cursor(): HTMLElement {
  const stops = screen.getByRole('grid').querySelectorAll<HTMLElement>('[tabindex="0"]');
  expect(stops).toHaveLength(1);
  expect(stops[0]?.tagName).toBe('TD');

  return stops[0] as HTMLElement;
}

function at(cell: HTMLElement): [number, number] {
  const row = cell.closest('tr') as HTMLTableRowElement;

  return [Array.from(screen.getByRole('grid').querySelectorAll('tbody tr[data-row]')).indexOf(row), (cell as HTMLTableCellElement).cellIndex];
}

describe('the cell cursor', () => {
  it('makes the Orders table a grid with exactly one cell in the tab order', () => {
    renderPage('Resources/Index', orders(3), { url: '/lathe/resources/orders' });

    const grid = screen.getByRole('grid');
    expect(grid.getAttribute('aria-rowcount')).toBe('-1');
    expect(cursor().textContent).toBe('Number 1');
    // Nothing else inside the rows takes a Tab: the checkboxes and row links wait for the cursor.
    expect(Array.from(grid.querySelectorAll('tbody input, tbody a, tbody button')).filter((element) => (element as HTMLElement).tabIndex >= 0)).toEqual([]);
  });

  it("takes a related record's link out of the tab order once the cursor's code is in", async () => {
    const base = orders(2);
    const linked = {
      ...base,
      table: {
        ...base.table,
        columns: base.table.columns.map((column) => (column.key === 'customer' ? { ...column, component: 'belongs-to-field' } : column)),
        rows: base.table.rows.map((row) => ({ ...row, cells: { ...row.cells, customer: { value: { value: row.id, display: `Ann ${String(row.id)}`, href: `/lathe/resources/customers/${String(row.id)}` }, display: `Ann ${String(row.id)}` } } })),
      },
    };
    renderPage('Resources/Index', linked, { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
    });

    const links = Array.from(screen.getByRole('grid').querySelectorAll<HTMLElement>('tbody a'));
    expect(links.length).toBeGreaterThan(0);
    expect(links.filter((link) => link.tabIndex >= 0)).toEqual([]);
  });

  it("moves the cursor from a first click on a related record's link, and opens it from a click once its cell holds the cursor", async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const base = orders(2);
    renderPage(
      'Resources/Index',
      {
        ...base,
        table: {
          ...base.table,
          columns: base.table.columns.map((column) => (column.key === 'customer' ? { ...column, component: 'belongs-to-field' } : column)),
          rows: base.table.rows.map((row) => ({ ...row, cells: { ...row.cells, customer: { value: { value: 7, display: 'Ann Lee', href: '/lathe/resources/customers/7' }, display: 'Ann Lee' } } })),
        },
      },
      { url: '/lathe/resources/orders' },
    );
    await waitFor(() => {
      expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
    });
    const link = screen.getAllByRole('link', { name: 'Ann Lee' })[1] as HTMLElement;

    fireEvent.pointerDown(link);
    fireEvent.click(link, { detail: 1 });
    expect(visit).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(link.closest('td'));

    fireEvent.pointerDown(link);
    fireEvent.click(link, { detail: 1 });
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/customers/7');
  });

  it('reaches the header from the first row, presses its select-all with Enter, and leaves its controls out of the tab order', async () => {
    renderPage('Resources/Index', orders(3), { url: '/lathe/resources/orders' });
    act(() => {
      cursor().focus();
    });

    await press('ArrowUp');
    expect(document.activeElement?.textContent).toBe('Number');
    await press('Home');
    await press('Enter');

    expect(screen.getAllByRole<HTMLInputElement>('checkbox').every((box) => box.checked)).toBe(true);
    expect(Array.from(screen.getByRole('grid').querySelectorAll<HTMLElement>('thead input, thead button')).filter((control) => control.tabIndex >= 0)).toEqual([]);
    await press('ArrowDown');
    expect(document.activeElement?.closest('tr')?.hasAttribute('data-row')).toBe(true);
  });

  it('moves down twice to row 3 in the same column, and Home, End and the page keys move along the row and the rows', async () => {
    renderPage('Resources/Index', orders(30), { url: '/lathe/resources/orders' });
    act(() => {
      cursor().focus();
    });

    await press('ArrowDown');
    await press('ArrowDown');
    expect(document.activeElement?.textContent).toBe('Number 3');
    expect(at(cursor())).toEqual([2, 1]);

    await press('ArrowRight');
    expect(document.activeElement?.textContent).toBe('Customer 3');
    await press('End');
    expect(at(cursor())).toEqual([2, 6]);
    await press('Home');
    expect(at(cursor())).toEqual([2, 0]);

    await press('PageDown');
    expect(at(cursor())).toEqual([27, 0]);
    await press('PageDown');
    expect(at(cursor())).toEqual([29, 0]);
    await press('PageUp');
    expect(at(cursor())).toEqual([4, 0]);
    await press('ArrowUp');
    await press('ArrowLeft');
    expect(at(cursor())).toEqual([3, 0]);
  });

  it('moves the cursor to a clicked cell and stays on the list', async () => {
    renderPage('Resources/Index', orders(3), { url: '/lathe/resources/orders' });
    await press('Tab');

    fireEvent.click(screen.getByText('Customer 2'));

    expect(document.activeElement?.textContent).toBe('Customer 2');
    expect(cursor().textContent).toBe('Customer 2');
    expect(requests).toHaveLength(0);
  });

  it('runs the click action from Enter on a cell that cannot be edited, as a click on the title link does', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage('Resources/Index', orders(3), { url: '/lathe/resources/orders' });
    act(() => {
      screen.getByText('Customer 2').closest('td')?.focus();
    });

    await press('Enter');
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/2');

    fireEvent.click(screen.getByText('Number 3'));
    expect(visit.mock.calls[1]?.[0]).toBe('/lathe/resources/orders/3');
  });

  it('opens the keys sheet from ? on the grid, while ? in the search box is just typed', async () => {
    renderPage('Resources/Index', { ...orders(3), resource: { ...orders(3).resource, searchable: true } }, { url: '/lathe/resources/orders' });
    const search = screen.getByRole('searchbox');
    fireEvent.keyDown(search, { key: '?' });
    expect(screen.queryByRole('dialog', { name: 'Keyboard shortcuts' })).toBeNull();

    act(() => {
      cursor().focus();
    });
    await press('?');

    const sheet = await screen.findByRole('dialog', { name: 'Keyboard shortcuts' });
    for (const group of ['Move', 'Edit', 'Select', 'Undo', 'Open']) {
      expect(within(sheet).getByRole('heading', { name: group })).toBeDefined();
    }
  });

  it('keeps one tab stop when the first group is folded, and its fold button in reach', async () => {
    renderPage('Resources/Index', orders(5, true), { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
    });

    fireEvent.click(screen.getByRole('button', { name: 'Status: paid' }));

    expect(cursor().textContent).toBe('Number 1');
    expect(screen.getByRole('button', { name: 'Status: pending' }).tabIndex).toBe(0);
  });

  it("enters a row's action cell with Enter, steps between its links with the arrows and leaves with Escape", async () => {
    renderPage('Resources/Index', orders(2), { url: '/lathe/resources/orders' });
    act(() => {
      cursor().focus();
    });

    await press('End');
    await press('Enter');
    expect(document.activeElement).toBe(screen.getByRole('link', { name: 'View ORD-1' }));
    await press('ArrowRight');
    expect(document.activeElement).toBe(screen.getByRole('link', { name: 'Edit ORD-1' }));
    await press('Escape');
    expect(document.activeElement?.hasAttribute('data-row-menu')).toBe(true);

    // Enter on a focused link follows it, a keyboard click the grid leaves alone.
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    await press('Enter');
    fireEvent.click(document.activeElement as HTMLElement, { detail: 0 });
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/1');
  });

  it('steps from the last row of a group to the first row of the next, past the group header', async () => {
    renderPage('Resources/Index', orders(5, true), { url: '/lathe/resources/orders' });
    // paid: 3, 4; pending: 1, 2; shipped: 5.
    act(() => {
      screen.getByText('Number 4').closest('td')?.focus();
    });

    await press('ArrowDown');

    expect(document.activeElement?.textContent).toBe('Number 1');
  });
});
