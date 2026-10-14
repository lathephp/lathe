import { router } from '@inertiajs/react';
import { __panel, on } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

beforeEach(() => {
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
  vi.spyOn(router, 'get').mockImplementation(() => undefined);
});

afterEach(() => {
  vi.restoreAllMocks();
  __panel.toasts.set([]);
});

const sendReport = {
  uriKey: 'send-report',
  name: 'Send Report',
  icon: null,
  description: null,
  group: null,
  destructive: false,
  standalone: true,
  sole: false,
  pinned: false,
  withoutConfirmation: true,
  confirmText: null,
  confirmButtonText: 'Run Action',
  cancelButtonText: 'Cancel',
  size: '2xl',
  style: 'window',
  asBlob: false,
  showOnIndex: true,
  showOnDetail: true,
  showOnTableRow: false,
};

function renderOrders(rowCount: number, url = '/lathe/resources/orders') {
  const rows = Array.from({ length: rowCount }, (_, index) => ({ id: index + 1, title: `#${String(index + 1)}`, cells: {}, abilities: { view: true }, actions: [] }));

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
      table: { columns: [], sortKeys: [], rows, rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: rowCount, exact: true, capped: false, estimated: false },
      actions: { actions: [sendReport], pivotActions: [], counts: { standalone: 1, sole: 0, resource: 0 } },
    },
    { url },
  );
}

describe('panel events', () => {
  it('emits actions:ran after an action run', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      new Response(JSON.stringify({ result: { message: 'Sent.', level: 'success', effect: null }, permitted: 1, skipped: 0, failed: 0, errors: [] }), { status: 200 }),
    );
    const heard = vi.fn();
    const stop = on('actions:ran', heard);
    renderOrders(2);

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));
    fireEvent.click(within(screen.getByRole('menu', { name: 'Actions' })).getByRole('menuitem', { name: 'Send Report' }));

    await waitFor(() => {
      expect(heard).toHaveBeenCalledWith({ action: 'send-report' });
    });
    stop();
  });

  it('emits filters:changed when the index filters change', () => {
    const heard = vi.fn();
    const stop = on('filters:changed', heard);
    renderOrders(0, '/lathe/resources/orders?filter%5Bstatus%5D=paid');

    fireEvent.click(screen.getByRole('button', { name: 'Clear filters' }));

    expect(heard).toHaveBeenCalledWith({ resource: 'orders', filters: {} });
    stop();
  });
});
