import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { ColumnPicker } from '../index/lazy';
import { renderPage } from './support';

// The picker loads on its first open; loaded once, it draws on the click itself.
beforeAll(async () => {
  await ColumnPicker.preload();
});

afterEach(() => {
  vi.restoreAllMocks();
});

const allColumns = [
  { key: 'number', label: 'Number' },
  { key: 'customer', label: 'Customer' },
  { key: 'status', label: 'Status' },
  { key: 'total_cents', label: 'Total' },
  { key: 'placed_at', label: 'Placed' },
];

const keys = ['number', 'customer', 'status', 'total_cents', 'placed_at'];

function column(key: string, label: string, pinned?: boolean) {
  return { key, label, component: 'text', sortable: false, sortKey: key, ...(pinned === undefined ? {} : { pinned }) };
}

function row(id: number) {
  const number = `ORD-${String(id).padStart(5, '0')}`;

  return {
    id,
    title: number,
    cells: {
      number: { value: number, display: number },
      customer: { value: id, display: `Customer ${String(id)}` },
      status: { value: 'paid', display: 'Paid' },
      total_cents: { value: 1000 * id, display: `$${String(id)}.00` },
      placed_at: { value: '2026-10-01', display: 'Oct 1, 2026' },
    },
    abilities: { view: true },
  };
}

interface Table {
  columns?: ReturnType<typeof column>[];
  columnChoice?: { order: string[]; hidden: string[]; pinned: string[] } | null;
  allColumns?: { key: string; label: string }[];
}

function renderOrders(table: Table = {}) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: false,
        abilities: { create: false },
        definition: {
          debounce: 0.5,
          paginationType: 'links',
          clickAction: 'detail',
          polling: null,
          showPollingToggle: false,
          tableStyle: 'default',
          showColumnBorders: false,
          softDeletes: false,
        },
      },
      table: {
        columns: table.columns ?? allColumns.map((entry) => column(entry.key, entry.label)),
        sortKeys: [],
        rows: [row(1), row(2)],
        rowKey: 'id',
        columnChoice: table.columnChoice ?? null,
        allColumns: table.allColumns ?? allColumns,
      },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: 2, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );
}

function bodyRows(): HTMLElement[] {
  const [, body] = screen.getAllByRole('rowgroup');

  return within(body as HTMLElement).queryAllByRole('row');
}

function headers(): HTMLElement[] {
  return screen.getAllByRole('columnheader');
}

function calls(fetch: { mock: { calls: unknown[][] } }) {
  return fetch.mock.calls.map((call) => {
    const init = call[1] as RequestInit | undefined;

    return { url: call[0], method: init?.method, body: typeof init?.body === 'string' ? (JSON.parse(init.body) as unknown) : undefined };
  });
}

function openPicker(): HTMLElement {
  fireEvent.click(screen.getByRole('button', { name: 'Columns' }));

  return screen.getByRole('dialog', { name: 'Columns' });
}

function saving() {
  const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response('{}', { status: 200 })));

  return { reload, fetch };
}

describe('column picker', () => {
  it('shows the code columns in order with the Columns button in the last header cell', () => {
    renderOrders();

    expect(headers().map((cell) => cell.textContent)).toEqual(['', 'Number', 'Customer', 'Status', 'Total', 'Placed', 'Actions']);
    expect(within(headers().at(-1) as HTMLElement).getByRole('button', { name: 'Columns' })).toBeTruthy();
  });

  it('saves a hidden column, then a pinned one, and reloads the table each time', async () => {
    const { reload, fetch } = saving();
    renderOrders();

    const picker = openPicker();
    expect(picker.contains(document.activeElement)).toBe(true);
    fireEvent.click(within(picker).getByRole('checkbox', { name: 'Customer' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(reload).toHaveBeenCalledWith({ only: ['table'] });

    fireEvent.click(within(picker).getByRole('button', { name: 'Pin Total' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(2);
    });
    expect(calls(fetch)).toEqual([
      { url: '/lathe/api/resources/orders/columns', method: 'PUT', body: { order: keys, hidden: ['customer'], pinned: [] } },
      { url: '/lathe/api/resources/orders/columns', method: 'PUT', body: { order: keys, hidden: ['customer'], pinned: ['total_cents'] } },
    ]);
    expect(within(picker).getByRole('checkbox', { name: 'Customer' })).toHaveProperty('checked', false);
    expect(within(picker).getByRole('button', { name: 'Pin Total' }).getAttribute('aria-pressed')).toBe('true');
  });

  it('sends a second change only once the first has saved, so they land in order', async () => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    let finish: (response: Response) => void = () => undefined;
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementationOnce(() => new Promise<Response>((resolve) => (finish = resolve)))
      .mockImplementation(() => Promise.resolve(new Response('{}', { status: 200 })));
    renderOrders();

    const picker = openPicker();
    fireEvent.click(within(picker).getByRole('checkbox', { name: 'Customer' }));
    fireEvent.click(within(picker).getByRole('checkbox', { name: 'Status' }));
    await new Promise((resolve) => setTimeout(resolve, 20));

    expect(fetch).toHaveBeenCalledTimes(1);

    finish(new Response('{}', { status: 200 }));

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(2);
    });
    expect(calls(fetch)[1]?.body).toEqual({ order: keys, hidden: ['customer', 'status'], pinned: [] });
  });

  it('puts a pinned column after the first and keeps it and the row menu in view', () => {
    renderOrders({
      columns: [column('number', 'Number', false), column('status', 'Status', false), column('total_cents', 'Total', true), column('placed_at', 'Placed', false)],
      columnChoice: { order: keys, hidden: ['customer'], pinned: ['total_cents'] },
    });

    expect(headers().map((cell) => cell.textContent)).toEqual(['', 'Number', 'Total', 'Status', 'Placed', 'Actions']);
    const cells = within(bodyRows()[0] as HTMLElement).getAllByRole('cell');
    expect(cells.map((cell) => cell.textContent)).toEqual(['', 'ORD-00001', '$1.00', 'Paid', 'Oct 1, 2026', '']);
    expect(headers()[2]?.classList.contains('sticky')).toBe(true);
    expect(cells[2]?.classList.contains('sticky')).toBe(true);
    expect(headers()[3]?.classList.contains('sticky')).toBe(false);
    expect(cells[3]?.classList.contains('sticky')).toBe(false);
    expect(headers()[5]?.className).toContain('sticky end-0');
    expect(cells[5]?.className).toContain('sticky end-0');
    // A sticky cell holds its popover in its own stacking context, so the cell rises over the cells after it.
    expect(headers()[5]?.classList.contains('z-20')).toBe(true);
    expect(cells[5]?.classList.contains('focus-within:z-10')).toBe(true);
    // The stuck cells sit above the cells that scroll beneath them, an inline select's button included.
    for (const cell of [cells[0], cells[1], cells[2], cells[5]]) {
      expect(cell?.classList.contains('z-[1]')).toBe(true);
    }
  });

  it('resets to the default columns', async () => {
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response(null, { status: 204 })));
    renderOrders({ columnChoice: { order: keys, hidden: ['customer'], pinned: [] } });

    const picker = openPicker();
    expect(within(picker).getByRole('checkbox', { name: 'Customer' })).toHaveProperty('checked', false);
    fireEvent.click(within(picker).getByRole('button', { name: 'Reset to default' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith({ only: ['table'] });
    });
    expect(calls(fetch)).toEqual([{ url: '/lathe/api/resources/orders/columns', method: 'DELETE', body: undefined }]);
    expect(within(picker).getByRole('checkbox', { name: 'Customer' })).toHaveProperty('checked', true);
  });

  it('moves a column down with Alt+ArrowDown on its grip and says so', async () => {
    const { reload, fetch } = saving();
    renderOrders();

    const picker = openPicker();
    fireEvent.keyDown(within(picker).getByRole('button', { name: 'Reorder Customer' }), { key: 'ArrowDown', altKey: true });

    await waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls(fetch)).toEqual([
      { url: '/lathe/api/resources/orders/columns', method: 'PUT', body: { order: ['number', 'status', 'customer', 'total_cents', 'placed_at'], hidden: [], pinned: [] } },
    ]);
    expect(within(picker).getAllByRole('checkbox').map((box) => box.closest('label')?.textContent)).toEqual([
      'Number',
      'Status',
      'Customer',
      'Total',
      'Placed',
    ]);
    expect(screen.getByText('Moved Customer to position 3').getAttribute('aria-live')).toBe('polite');
  });

  it('offers no picker on a lens', () => {
    renderOrders({ allColumns: [] });

    expect(screen.queryByRole('button', { name: 'Columns' })).toBeNull();
  });
});
