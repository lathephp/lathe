import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/react';
import { choose, fakeInertiaServer, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

const status = { key: 'status', attribute: 'status', label: 'Status', component: 'select-field', sortable: false, sortKey: 'status', inline: true, options: [{ value: 'pending', label: 'pending' }, { value: 'shipped', label: 'shipped' }] };
const name = { key: 'name', attribute: 'name', label: 'Name', component: 'text-field', sortable: false, sortKey: 'name', inline: true };
const active = { key: 'active', attribute: 'active', label: 'Active', component: 'boolean-field', sortable: false, sortKey: 'active', inline: true, asCheckbox: false, trueValue: true };
const vip = { ...active, key: 'vip', attribute: 'vip', label: 'VIP', asCheckbox: true };

function renderRows(update = true) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: {
        columns: [status, name, active, vip],
        rows: [
          {
            id: 12,
            title: 'ORD-00012',
            cells: { status: { value: 'pending', display: 'pending' }, name: { value: 'Ann', display: 'Ann' }, active: { value: false, display: 'No' }, vip: { value: false, display: 'No' } },
            abilities: { view: true, update },
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

function reply(status: number, body: unknown) {
  return new Response(JSON.stringify(body), { status });
}

function bodies(fetch: { mock: { calls: unknown[][] } }): Record<string, unknown>[] {
  return fetch.mock.calls.map((call) => {
    const init = call[1] as RequestInit | undefined;

    return JSON.parse(typeof init?.body === 'string' ? init.body : '{}') as Record<string, unknown>;
  });
}

describe('inline cells', () => {
  it('saves a select on choice, sends the row token and shows the fresh row', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      reply(200, { field: 'status', fields: { status: { value: 'shipped', display: 'shipped' }, name: { value: 'Ann', display: 'Ann' } }, updatedAt: '2026-10-14T09:00:02.000Z' }),
    );
    renderRows();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');

    await waitFor(() => {
      expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('shipped');
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/12/field/status');
    expect(bodies(fetch)[0]).toEqual({ value: 'shipped', _updated_at: '2026-10-14T09:00:00.000Z' });
  });

  it('saves text on Enter, reverts on Escape, and shows a refused value as a tooltip with the old value back', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(reply(200, { field: 'name', fields: { name: { value: 'Ann Lee', display: 'Ann Lee' } }, updatedAt: '2026-10-14T09:00:02.000Z' }))
      .mockResolvedValueOnce(reply(422, { message: 'The name field is required.', errors: { name: ['The name field is required.'] } }));
    renderRows();

    fireEvent.click(screen.getByRole('button', { name: 'Edit Name' }));
    fireEvent.keyDown(screen.getByRole('textbox', { name: 'Name' }), { key: 'Escape' });
    expect(fetch).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Edit Name' }));
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Ann Lee' } });
    fireEvent.keyDown(screen.getByRole('textbox', { name: 'Name' }), { key: 'Enter' });
    expect(await screen.findByText('Ann Lee')).toBeDefined();

    fireEvent.click(screen.getByRole('button', { name: 'Edit Name' }));
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: '' } });
    fireEvent.keyDown(screen.getByRole('textbox', { name: 'Name' }), { key: 'Enter' });

    expect((await screen.findByRole('tooltip')).textContent).toBe('The name field is required.');
    expect(screen.getByText('Ann Lee')).toBeDefined();
    expect(bodies(fetch)[1]?._updated_at).toBe('2026-10-14T09:00:02.000Z');
  });

  it('toggles a boolean at once, draws asCheckbox as a checkbox, and keeps one request in flight', async () => {
    let answer: (response: Response) => void = () => undefined;
    const fetch = vi.spyOn(globalThis, 'fetch').mockReturnValue(
      new Promise((resolve) => {
        answer = resolve;
      }),
    );
    renderRows();

    expect(screen.getByRole('checkbox', { name: 'VIP' })).toBeDefined();
    const toggle = screen.getByRole('switch', { name: 'Active' });
    fireEvent.click(toggle);
    fireEvent.click(toggle);

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(bodies(fetch)[0]?.value).toBe(true);
    await act(async () => {
      answer(reply(200, { field: 'active', fields: { active: { value: true, display: 'Yes' } }, updatedAt: null }));
      await Promise.resolve();
    });
    await waitFor(() => {
      expect(screen.getByRole('switch', { name: 'Active' }).getAttribute('aria-checked')).toBe('true');
    });
  });

  it('reverts a 5xx and toasts', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(reply(500, { message: 'Server Error' }));
    renderRows();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Something went wrong. Please try again.');
    });
    expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('pending');
  });

  it('refreshes the row from a 409 and sends its new token next time', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(reply(409, { error: 'conflict', message: 'Changed.', updatedAt: '2026-10-14T09:00:05.000Z', fields: { status: { value: 'shipped', display: 'shipped' } } }))
      .mockResolvedValueOnce(reply(200, { field: 'status', fields: { status: { value: 'pending', display: 'pending' } }, updatedAt: '2026-10-14T09:00:06.000Z' }));
    renderRows();

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'shipped');
    await waitFor(() => {
      expect(screen.getByRole('combobox', { name: 'Status' }).textContent).toBe('shipped');
    });

    await choose(screen.getByRole('combobox', { name: 'Status' }), 'pending');
    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(2);
    });
    expect(bodies(fetch)[1]?._updated_at).toBe('2026-10-14T09:00:05.000Z');
  });

  it('shows plain cells to a user who may not update the row', () => {
    renderRows(false);

    expect(screen.queryByRole('combobox', { name: 'Status' })).toBeNull();
    expect(screen.getByText('pending')).toBeDefined();
  });

  it('shows the cells a later reload brings over the cells an inline save sent back', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(reply(200, { field: 'name', fields: { name: { value: 'Ann Lee', display: 'Ann Lee' } }, updatedAt: '2026-10-14T09:00:02.000Z' }));
    const requests = fakeInertiaServer();
    renderRows();
    window.history.replaceState(null, '', '/lathe/resources/orders');

    fireEvent.click(screen.getByRole('button', { name: 'Edit Name' }));
    fireEvent.change(screen.getByRole('textbox', { name: 'Name' }), { target: { value: 'Ann Lee' } });
    fireEvent.keyDown(screen.getByRole('textbox', { name: 'Name' }), { key: 'Enter' });
    expect(await screen.findByText('Ann Lee')).toBeDefined();

    // A queued run renamed the row since, and the index reloads its rows.
    router.reload({ only: ['table'] });
    await waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    await act(async () => {
      requests[0]?.respond(200, {
        component: 'Resources/Index',
        url: '/lathe/resources/orders',
        props: {
          table: {
            columns: [status, name, active, vip],
            rows: [
              {
                id: 12,
                title: 'ORD-00012',
                cells: { status: { value: 'shipped', display: 'shipped' }, name: { value: 'Ann Lee-Park', display: 'Ann Lee-Park' }, active: { value: false, display: 'No' }, vip: { value: false, display: 'No' } },
                abilities: { view: true, update: true },
                updatedAt: '2026-10-14T09:05:00.000Z',
              },
            ],
            rowKey: 'id',
            sortKeys: [],
          },
        },
      });
      await Promise.resolve();
    });

    expect(await screen.findByText('Ann Lee-Park')).toBeDefined();
  });
});
