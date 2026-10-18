import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { choose, renderPage } from './support';

beforeEach(() => {
  __panel.toasts.set([]);
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const options = ['paid', 'shipped', 'cancelled'].map((value) => ({ value, label: value }));
const status = { key: 'status', attribute: 'status', label: 'Status', component: 'select-field', sortable: false, sortKey: 'status', inline: true, options };

function renderOrder() {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: {
        columns: [status],
        rows: [{ id: 12, title: 'ORD-00012', cells: { status: { value: 'paid', display: 'paid' } }, abilities: { view: true, update: true }, updatedAt: '2026-10-14T09:00:00.000Z' }],
        rowKey: 'id',
        sortKeys: [],
      },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );
}

const saved = (value: string, updatedAt: string) => new Response(JSON.stringify({ field: 'status', fields: { status: { value, display: value } }, updatedAt }), { status: 200 });

function bodies(fetch: { mock: { calls: unknown[][] } }): Record<string, unknown>[] {
  return fetch.mock.calls.map((call) => JSON.parse((call[1] as { body: string }).body) as Record<string, unknown>);
}

describe('undoing an inline edit', () => {
  it('shows Saving… while the save is pending', async () => {
    let finish: (response: Response) => void = () => undefined;
    vi.spyOn(globalThis, 'fetch').mockReturnValue(new Promise<Response>((resolve) => (finish = resolve)));
    renderOrder();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');

    expect(await screen.findByText('Saving…')).toBeTruthy();

    act(() => {
      finish(saved('shipped', '2026-10-14T09:00:02.000Z'));
    });
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });
  });

  it('offers Undo in the toast, and Undo saves the old value with the token the first save returned', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(saved('shipped', '2026-10-14T09:00:02.000Z')).mockResolvedValueOnce(saved('paid', '2026-10-14T09:00:04.000Z'));
    renderOrder();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');

    await waitFor(() => {
      expect(__panel.toasts.get()[0]?.action?.label).toBe('Undo');
    });
    expect(__panel.toasts.get()[0]?.message).toBe('Status changed to shipped.');

    act(() => {
      __panel.toasts.get()[0]?.action?.onClick();
    });

    await waitFor(() => {
      expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('paid');
    });
    expect(bodies(fetch)[1]).toEqual({ value: 'paid', _updated_at: '2026-10-14T09:00:02.000Z' });
  });

  it('opens the conflict dialog when someone else changed the record, and Refresh keeps their value', async () => {
    const conflict = new Response(JSON.stringify({ error: 'conflict', fields: { status: { value: 'cancelled', display: 'cancelled' } }, updatedAt: '2026-10-14T09:00:05.000Z' }), { status: 409 });
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(saved('shipped', '2026-10-14T09:00:02.000Z')).mockResolvedValueOnce(conflict);
    renderOrder();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');
    await waitFor(() => {
      expect(__panel.toasts.get()[0]?.action).toBeDefined();
    });
    act(() => {
      __panel.toasts.get()[0]?.action?.onClick();
    });

    expect(await screen.findByText('This record has changed')).toBeTruthy();
    expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('cancelled');

    fireEvent.click(screen.getByRole('button', { name: 'Refresh' }));

    await waitFor(() => {
      expect(screen.queryByText('This record has changed')).toBeNull();
    });
    expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('cancelled');
    expect(fetch).toHaveBeenCalledTimes(2);
  });
});
