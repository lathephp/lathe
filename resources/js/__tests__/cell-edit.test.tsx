import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cellOf, editCell, pick, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const number = { key: 'number', attribute: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number' };
const status = {
  key: 'status',
  attribute: 'status',
  label: 'Status',
  component: 'select-field',
  sortable: false,
  sortKey: 'status',
  inline: true,
  nullable: false,
  options: [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'cancelled', label: 'Cancelled' },
  ],
  badges: { pending: 'warning', paid: 'info', shipped: 'success', cancelled: 'neutral' },
  meter: ['pending', 'paid', 'shipped'],
};
const note = { key: 'note', attribute: 'note', label: 'Note', component: 'text-field', sortable: false, sortKey: 'note', inline: true };
const placed = { key: 'placed', attribute: 'placed', label: 'Placed', component: 'text-field', sortable: false, sortKey: 'placed' };

function renderOrders(abilities: Record<string, boolean> = { view: true, update: true, inlineEdit: true }, lens: { uriKey: string; name: string } | null = null) {
  return renderPage(
    'Resources/Index',
    {
      lens,
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: {
        columns: [number, status, note, placed],
        rows: [
          {
            id: 12,
            title: 'ORD-00012',
            cells: { number: { value: 'ORD-00012', display: 'ORD-00012' }, status: { value: 'pending', display: 'Pending' }, note: { value: 'Gift', display: 'Gift' }, placed: { value: 'Oct 14', display: 'Oct 14' } },
            abilities,
            updatedAt: '2026-10-14T09:00:00.000Z',
          },
        ],
        rowKey: 'id',
        sortKeys: [],
      },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );
}

const ticksOn = () => cellOf('Status').querySelectorAll('[data-lathe-meter-tick][data-on]').length;

describe('editing a cell in place', () => {
  it('shows the value until Enter opens the 4 options; Paid sends 1 PATCH and the meter fills before the reply', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockReturnValue(new Promise(() => undefined));
    renderOrders();

    expect(screen.queryByRole('combobox', { name: 'Status' })).toBeNull();
    expect(ticksOn()).toBe(1);

    await editCell('Status');
    expect(await screen.findAllByRole('option')).toHaveLength(4);
    await pick('Paid');

    expect(fetch).toHaveBeenCalledTimes(1);
    await waitFor(() => {
      expect(ticksOn()).toBe(2);
    });
    // The list closed onto the cell, so the cursor stays where the edit began.
    expect(document.activeElement).toBe(cellOf('Status'));
  });

  it('cancels on Escape with no request, and the cell still reads Pending', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    renderOrders();

    await editCell('Status', 'F2');
    fireEvent.keyDown(await screen.findByRole('listbox'), { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
    expect(fetch).not.toHaveBeenCalled();
    expect(cellOf('Status').textContent).toBe('Pending');
  });

  it('starts no edit from a letter or a number key', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    renderOrders();

    await editCell('Status', 'p');
    await editCell('Status', '2');

    expect(screen.queryByRole('listbox')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
    expect(cellOf('Status').textContent).toBe('Pending');
  });

  it('opens text from a double-click, and Tab saves it and moves the cursor right', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ field: 'note', fields: { note: { value: 'Gift wrap', display: 'Gift wrap' } }, updatedAt: '2026-10-14T09:00:02.000Z' }), { status: 200 }));
    renderOrders();
    await editCell('Note', 'Shift');

    fireEvent.doubleClick(cellOf('Note'));
    const input = await screen.findByRole('textbox', { name: 'Note' });
    fireEvent.change(input, { target: { value: 'Gift wrap' } });
    fireEvent.keyDown(input, { key: 'Tab' });

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(document.activeElement).toBe(cellOf('Placed'));
    expect(await screen.findByText('Gift wrap')).toBeDefined();
  });

  it('opens the order from Enter on a Number cell, and from a Status cell for a user without inlineEdit', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ view: true, update: true, inlineEdit: false });

    await editCell('Number');
    await editCell('Status');

    expect(visit.mock.calls.map((call) => call[0])).toEqual(['/lathe/resources/orders/12', '/lathe/resources/orders/12']);
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('serves the Pending Orders lens with the same grid', async () => {
    renderOrders(undefined, { uriKey: 'pending-orders', name: 'Pending Orders' });

    expect(screen.getByRole('heading', { name: 'Pending Orders' })).toBeDefined();
    await editCell('Status');

    expect(await screen.findAllByRole('option')).toHaveLength(4);
  });
});
