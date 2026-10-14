import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ActionMeta } from '../actions/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  __panel.toasts.set([]);
});

function meta(uriKey: string, name: string, extra: Partial<ActionMeta> = {}): ActionMeta {
  return {
    uriKey,
    name,
    icon: null,
    description: null,
    group: null,
    destructive: false,
    standalone: false,
    sole: false,
    pinned: false,
    withoutConfirmation: false,
    confirmText: null,
    confirmButtonText: 'Run Action',
    cancelButtonText: 'Cancel',
    size: '2xl',
    style: 'window',
    asBlob: false,
    showOnIndex: true,
    showOnDetail: true,
    showOnTableRow: false,
    ...extra,
  };
}

const actions = [
  meta('send-report', 'Send Report', { standalone: true, withoutConfirmation: true }),
  meta('export', 'Export', { description: 'Download the rows' }),
  meta('mark-shipped', 'Mark Shipped', { group: 'Fulfilment', confirmText: 'Mark :count orders as shipped?' }),
  meta('hold-order', 'Hold order', { group: 'Fulfilment', sole: true }),
  meta('cancel-order', 'Cancel Order', { destructive: true }),
];

function rows(count: number, flags = ['export', 'mark-shipped', 'hold-order', 'cancel-order']) {
  return Array.from({ length: count }, (_, index) => {
    const number = `ORD-${String(index + 1).padStart(5, '0')}`;

    return { id: index + 1, title: number, cells: { number: { value: number, display: number } }, abilities: { view: true }, actions: flags };
  });
}

function renderOrders(list = actions, pageRows = rows(3)) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25],
        searchable: false,
        abilities: { create: false },
        definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
      },
      table: { columns: [{ key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' }], sortKeys: [], rows: pageRows, rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: pageRows.length, exact: true, capped: false, estimated: false },
      actions: { actions: list, pivotActions: [], counts: { standalone: 1, sole: 1, resource: 3 } },
    },
    { url: '/lathe/resources/orders' },
  );
}

function tick(...ids: number[]) {
  const [, body] = screen.getAllByRole('rowgroup');
  const boxes = within(body as HTMLElement).getAllByRole('checkbox');

  for (const id of ids) {
    fireEvent.click(boxes[id - 1] as HTMLElement);
  }
}

async function openMenu() {
  fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));

  return screen.getByRole('menu', { name: 'Actions' });
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });

describe('the actions menu', () => {
  it('lists standalone first, Export ungrouped, Mark Shipped under Fulfilment and the destructive one last below a separator', async () => {
    renderOrders();
    tick(1, 2, 3);
    const menu = await openMenu();

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual([
      'Send Report',
      'ExportDownload the rows',
      'Mark Shipped',
      'Hold order',
      'Cancel Order',
    ]);
    expect(within(menu).getByText('Fulfilment')).toBeTruthy();
    expect(within(menu).getAllByRole('separator')).toHaveLength(1);
  });

  it('enables a standalone action with nothing ticked, a resource action once a row is, and a sole one at exactly one', async () => {
    renderOrders();
    let menu = await openMenu();
    const disabled = () => within(menu).getAllByRole<HTMLButtonElement>('menuitem').filter((item) => item.disabled).map((item) => item.textContent);

    expect(disabled()).toEqual(['ExportDownload the rows', 'Mark Shipped', 'Hold order', 'Cancel Order']);

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));
    tick(1);
    menu = await openMenu();
    expect(disabled()).toEqual([]);

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }));
    tick(2);
    menu = await openMenu();
    expect(disabled()).toEqual(['Hold order']);
  });

  it('disables an action a ticked row may not run', async () => {
    renderOrders(actions, [...rows(1), ...rows(1, ['export']).map((row) => ({ ...row, id: 2 }))]);
    tick(1, 2);
    const menu = await openMenu();

    expect(within(menu).getByRole<HTMLButtonElement>('menuitem', { name: 'Cancel Order' }).disabled).toBe(true);
  });

  it('renders nothing without a visible action', () => {
    renderOrders([]);

    expect(screen.queryByRole('button', { name: 'Actions' })).toBeNull();
  });

  it('shows a pinned action as a button once a row is ticked', async () => {
    renderOrders([...actions, meta('flag', 'Flag', { pinned: true })]);

    await screen.findByRole('button', { name: 'Actions' });
    expect(screen.queryByRole('button', { name: 'Flag' })).toBeNull();
    tick(1);
    expect(await screen.findByRole('button', { name: 'Flag' })).toBeTruthy();
  });
});

describe('the action modal', () => {
  it('confirms with the count, runs on the ticked ids and toasts the result', async () => {
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 3 orders as shipped?' }))
      .mockResolvedValueOnce(json({ result: { message: 'Action was executed successfully.', level: 'success', effect: null }, permitted: 3, skipped: 0, failed: 0, errors: [] }));
    renderOrders();
    tick(1, 2, 3);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark Shipped' }));

    const dialog = await screen.findByRole('dialog');
    expect(await within(dialog).findByText('Mark 3 orders as shipped?')).toBeTruthy();
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/actions/mark-shipped/form?count=3');

    fireEvent.click(within(dialog).getByRole('button', { name: 'Run Action' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Action was executed successfully.']);
    });
    const [url, init] = fetch.mock.calls[1] ?? [];
    expect(url).toBe('/lathe/api/resources/orders/action');
    expect(JSON.parse(init?.body as string)).toMatchObject({ action: 'mark-shipped', selection: { mode: 'ids', ids: [1, 2, 3] }, fields: {} });
    expect(reload).toHaveBeenCalled();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('keeps the modal open with the message under its field on a 422', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        json({
          fields: [{ key: 'note', attribute: 'note', label: 'Note', component: 'text-field', value: null, stacked: false, compact: false, help: null, required: true }],
          confirmText: null,
        }),
      )
      .mockResolvedValueOnce(json({ message: 'Invalid', errors: { note: ['The note field is required.'] } }, 422));
    renderOrders();
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark Shipped' }));
    const dialog = await screen.findByRole('dialog');
    await within(dialog).findByText('Note');

    fireEvent.click(within(dialog).getByRole('button', { name: 'Run Action' }));

    expect(await within(dialog).findByText('The note field is required.')).toBeTruthy();
    expect(screen.getByRole('dialog')).toBeTruthy();
  });

  it('starts the progress toast of a queued export', async () => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ fields: [], confirmText: null }))
      .mockResolvedValueOnce(json({ result: { message: 'Export started.', level: 'success', effect: null }, permitted: 0, skipped: 0, failed: 0, errors: [], export_run: { id: 7 } }));
    const started = vi.fn();
    window.addEventListener('lathe:export-started', started);
    renderOrders();
    tick(1, 2, 3);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: /Export/ }));
    const dialog = await screen.findByRole('dialog');
    await waitFor(() => {
      expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Run Action' }).disabled).toBe(false);
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Run Action' }));

    await waitFor(() => {
      expect(started).toHaveBeenCalledTimes(1);
    });
    window.removeEventListener('lathe:export-started', started);
    expect((started.mock.calls[0]?.[0] as CustomEvent).detail).toEqual({ id: 7 });
  });

  it('runs an action without confirmation at once and toasts Retry on a server error', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ message: 'Server Error' }, 500));
    renderOrders();
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Send Report' }));

    await waitFor(() => {
      expect(__panel.toasts.get()[0]?.action?.label).toBe('Retry');
    });
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('shows Preparing file while a file action without confirmation runs', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ result: { message: null, level: 'success', effect: null }, permitted: 0, skipped: 0, failed: 0, errors: [] }));
    renderOrders([meta('report', 'Download Report', { standalone: true, withoutConfirmation: true, asBlob: true })]);

    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Download Report' }));

    expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Preparing file');
  });
});
