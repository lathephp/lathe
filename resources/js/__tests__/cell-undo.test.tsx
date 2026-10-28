import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { runAction } from '../actions/runAction';
import { clearUndo } from '../index/undo';
import { cellOf, editCell, pick, renderPage } from './support';

beforeEach(() => {
  __panel.toasts.set([]);
});

afterEach(() => {
  // The stack is the tab's, so no test leaves an undo for the next.
  clearUndo();
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

function renderOrders(abilities: Record<string, boolean> = { view: true, update: true, inlineEdit: true }) {
  return renderPage(
    'Resources/Index',
    {
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


const reply = (value: string, updatedAt: string) =>
  new Response(JSON.stringify({ field: 'status', fields: { status: { value, display: value.charAt(0).toUpperCase() + value.slice(1) } }, updatedAt }), { status: 200 });

function patches(fetch: { mock: { calls: unknown[][] } }): Record<string, unknown>[] {
  return fetch.mock.calls
    .filter((call) => (call[1] as RequestInit | undefined)?.method === 'PATCH')
    .map((call) => JSON.parse((call[1] as { body: string }).body) as Record<string, unknown>);
}

const toasts = () => __panel.toasts.get().map((toast) => toast.message);

function undoKey() {
  fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
}

async function change(to: string) {
  await editCell('Status');
  await pick(to);
  await waitFor(() => {
    expect(screen.queryByText('Saving…')).toBeNull();
  });
}

describe('undoing cell edits', () => {
  it('sends the inverse PATCH from Ctrl+Z and says Change undone', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(reply('paid', 't1')).mockResolvedValueOnce(reply('pending', 't2'));
    renderOrders();

    await change('Paid');
    undoKey();

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    expect(patches(fetch)).toEqual([
      { value: 'paid', _updated_at: '2026-10-14T09:00:00.000Z' },
      { value: 'pending', _updated_at: 't1' },
    ]);
    expect(cellOf('Status').textContent).toBe('Pending');
  });

  it('restores two edits newest first, each against the token the last save returned', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(reply('paid', 't1'))
      .mockResolvedValueOnce(reply('shipped', 't2'))
      .mockResolvedValueOnce(reply('paid', 't3'))
      .mockResolvedValueOnce(reply('pending', 't4'));
    renderOrders();

    await change('Paid');
    await change('Shipped');
    undoKey();
    await waitFor(() => {
      expect(patches(fetch)).toHaveLength(3);
    });
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });
    undoKey();

    await waitFor(() => {
      expect(patches(fetch)).toHaveLength(4);
    });
    expect(patches(fetch).slice(2)).toEqual([
      { value: 'paid', _updated_at: 't2' },
      { value: 'pending', _updated_at: 't3' },
    ]);
  });

  it('sends the same PATCH from the toast Undo, and nothing more from Ctrl+Z after it', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(reply('paid', 't1')).mockResolvedValueOnce(reply('pending', 't2'));
    renderOrders();

    await change('Paid');
    __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    await waitFor(() => {
      expect(patches(fetch)).toHaveLength(2);
    });
    undoKey();

    expect(patches(fetch)[1]).toEqual({ value: 'pending', _updated_at: 't1' });
    expect(patches(fetch)).toHaveLength(2);
  });

  it('undoes nothing after an action runs, so the records it changed stay as it left them', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(reply('paid', 't1'));
    renderOrders();
    await change('Paid');

    fetch.mockResolvedValueOnce(new Response(JSON.stringify({ id: 'run-1', status: 'finished' }), { status: 200 }));
    await runAction('/lathe/api/resources/orders/action', 'mark-shipped', { mode: 'ids', ids: [12, 13, 14], exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: null, lens: null, via: null }, {});
    undoKey();

    expect(patches(fetch)).toHaveLength(1);
  });

  it('sends nothing from Ctrl+Z on a fresh page', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    renderOrders();

    undoKey();

    expect(fetch).not.toHaveBeenCalled();
  });

  it('opens This record has changed when someone else saved the order first', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'conflict', message: 'Changed.', updatedAt: 't9', fields: { status: { value: 'shipped', display: 'Shipped' } } }), { status: 409 }),
    );
    renderOrders();

    await editCell('Status');
    await pick('Paid');

    expect(await screen.findByRole('dialog', { name: 'This record has changed' })).toBeDefined();
  });
});
