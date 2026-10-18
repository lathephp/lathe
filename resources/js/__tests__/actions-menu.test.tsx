import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ActionMeta } from '../actions/types';
import { renderPage } from './support';

// A run reloads the rows through Inertia; no test here may send that request for real.
beforeEach(() => {
  vi.spyOn(router, 'reload').mockImplementation(() => undefined);
});

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
    expect(reload).toHaveBeenCalledWith({ only: ['table', 'count', 'tabCounts', 'summaries'] });
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('titles a confirm by its question, 400 px wide, with the verb as the rightmost button', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 3 orders as shipped?' }));
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmText: 'Mark :count orders as shipped?', confirmButtonText: 'Mark shipped', size: 'small' })], rows(3, ['mark-shipped']));
    tick(1, 2, 3);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark shipped' }));

    const dialog = await screen.findByRole('dialog', { name: 'Mark 3 orders as shipped?' });
    expect(within(dialog).getByRole('heading').textContent).toBe('Mark 3 orders as shipped?');
    expect(within(dialog).getAllByRole('button').at(-1)?.textContent).toBe('Mark shipped');
    expect(dialog.className).toContain('max-w-[400px]');
  });

  it('names the one record a destructive confirm runs on and holds the focus on its safe button', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: 'Cancel order ORD-00001?' }));
    renderOrders(
      [meta('cancel-order', 'Cancel order', { destructive: true, confirmButtonText: 'Cancel order', cancelButtonText: 'Keep order', size: 'small' })],
      rows(3, ['cancel-order']),
    );
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Cancel order' }));

    const dialog = await screen.findByRole('dialog', { name: 'Cancel order ORD-00001?' });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/actions/cancel-order/form?count=1&resourceId=1');
    expect(within(dialog).getAllByRole('button').map((button) => button.textContent)).toEqual(['Keep order', 'Cancel order']);
    await waitFor(() => {
      expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Cancel order' }).disabled).toBe(false);
    });
    expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: 'Keep order' }));
  });

  it('closes on Escape and gives the focus back to the button that opened it', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 1 orders as shipped?' }));
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmButtonText: 'Mark shipped', size: 'small' })], rows(1, ['mark-shipped']));
    tick(1);
    const trigger = await screen.findByRole('button', { name: 'Actions' });
    trigger.focus();
    fireEvent.click(trigger);
    fireEvent.click(within(screen.getByRole('menu', { name: 'Actions' })).getByRole('menuitem', { name: 'Mark shipped' }));
    await screen.findByRole('dialog');

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Actions' }));
  });

  it('stays open on Escape and a scrim click while the action runs', async () => {
    let finish: (response: Response) => void = () => undefined;
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 1 orders as shipped?' }))
      .mockReturnValueOnce(
        new Promise<Response>((resolve) => {
          finish = resolve;
        }),
      );
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmButtonText: 'Mark shipped', size: 'small' })], rows(1, ['mark-shipped']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark shipped' }));
    const dialog = await screen.findByRole('dialog');
    const run = within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Mark shipped' });
    await waitFor(() => {
      expect(run.disabled).toBe(false);
    });

    fireEvent.click(run);
    await waitFor(() => {
      expect(run.disabled).toBe(true);
    });
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });
    fireEvent.click(document.querySelector('.bg-scrim') as HTMLElement);

    expect(screen.getByRole('dialog')).toBe(dialog);
    finish(json({ result: { message: 'Done.', level: 'success', effect: null }, permitted: 1, skipped: 0, failed: 0, errors: [] }));
    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
  });

  it('closes only the Target picker on Escape inside it', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        json({
          fields: [{ key: 'target', attribute: 'target', label: 'Target', component: 'belongs-to-field', resource: 'tags', value: null, stacked: false, compact: false, help: null, required: true }],
          confirmText: null,
        }),
      )
      .mockResolvedValueOnce(json({ items: [{ value: 1, display: 'sale' }], has_more: false }));
    renderOrders([meta('merge-tags', 'Merge Tags', { size: 'medium' })], rows(1, ['merge-tags']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Merge Tags' }));
    fireEvent.mouseDown(await within(await screen.findByRole('dialog')).findByRole('combobox'), { button: 0 });
    // In a browser the open list takes the focus, as here.
    (await screen.findByRole('option', { name: 'sale' })).focus();

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
    expect(screen.getByRole('dialog', { name: 'Merge Tags' })).toBeTruthy();
  });

  it('closes only the searchable Target picker on Escape in its search box', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        json({
          fields: [
            { key: 'target', attribute: 'target', label: 'Target', component: 'belongs-to-field', resource: 'tags', searchable: true, value: null, stacked: false, compact: false, help: null, required: true },
          ],
          confirmText: null,
        }),
      )
      .mockResolvedValue(json({ items: [{ value: 1, display: 'sale' }], has_more: false }));
    renderOrders([meta('merge-tags', 'Merge Tags', { size: 'medium' })], rows(1, ['merge-tags']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Merge Tags' }));
    const dialog = await screen.findByRole('dialog');
    fireEvent.click(await within(dialog).findByRole('button', { name: 'Target' }));
    await within(dialog).findByRole('listbox');
    expect(document.activeElement).toBe(within(dialog).getByRole('searchbox'));

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    expect(within(dialog).queryByRole('listbox')).toBeNull();
    expect(screen.getByRole('dialog', { name: 'Merge Tags' })).toBe(dialog);
  });

  it('lets a picker hang below the dialog: the overlay scrolls, not the dialog', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: null }));
    renderOrders([meta('merge-tags', 'Merge Tags', { size: 'medium' })], rows(1, ['merge-tags']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Merge Tags' }));
    const dialog = await screen.findByRole('dialog');

    expect(dialog.className).not.toContain('overflow-y-auto');
    expect(dialog.parentElement?.className).toContain('overflow-y-auto');
  });

  it('asks for the password inside the open dialog and runs the action once it is confirmed', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 1 orders as shipped?' }))
      .mockResolvedValueOnce(json({ message: 'Password confirmation required.' }, 423))
      .mockResolvedValueOnce(new Response(null, { status: 201 }))
      .mockResolvedValueOnce(json({ result: { message: 'Done.', level: 'success', effect: null }, permitted: 1, skipped: 0, failed: 0, errors: [] }));
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmButtonText: 'Mark shipped', size: 'small' })], rows(1, ['mark-shipped']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark shipped' }));
    const run = await within(await screen.findByRole('dialog')).findByRole<HTMLButtonElement>('button', { name: 'Mark shipped' });
    await waitFor(() => {
      expect(run.disabled).toBe(false);
    });

    fireEvent.click(run);

    const dialog = await screen.findByRole('dialog', { name: 'Confirm your password' });
    expect(screen.getAllByRole('dialog')).toHaveLength(1);
    expect(within(dialog).queryByRole('button', { name: 'Mark shipped' })).toBeNull();
    expect(document.activeElement).toBe(within(dialog).getByLabelText('Password'));

    fireEvent.change(within(dialog).getByLabelText('Password'), { target: { value: 'password' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Confirm' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(fetch.mock.calls.map(([url]) => url)).toEqual([
      '/lathe/api/resources/orders/actions/mark-shipped/form?count=1&resourceId=1',
      '/lathe/api/resources/orders/action',
      '/lathe/confirm-password',
      '/lathe/api/resources/orders/action',
    ]);
    expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Done.']);
  });

  it('goes back to the dialog when the password check is cancelled', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ fields: [], confirmText: 'Mark 1 orders as shipped?' }))
      .mockResolvedValueOnce(json({ message: 'Password confirmation required.' }, 423));
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmButtonText: 'Mark shipped', size: 'small' })], rows(1, ['mark-shipped']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark shipped' }));
    const run = await within(await screen.findByRole('dialog')).findByRole<HTMLButtonElement>('button', { name: 'Mark shipped' });
    await waitFor(() => {
      expect(run.disabled).toBe(false);
    });
    fireEvent.click(run);
    const dialog = await screen.findByRole('dialog', { name: 'Confirm your password' });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    expect(await screen.findByRole('dialog', { name: 'Mark 1 orders as shipped?' })).toBe(dialog);
    await waitFor(() => {
      expect(within(dialog).getByRole<HTMLButtonElement>('button', { name: 'Mark shipped' }).disabled).toBe(false);
    });
    expect(dialog.contains(document.activeElement)).toBe(true);
    expect(__panel.toasts.get()).toEqual([]);
  });

  it('is a bottom sheet on a phone with 44 px buttons, the action on top', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: 'Cancel order ORD-00001?' }));
    renderOrders(
      [meta('cancel-order', 'Cancel order', { destructive: true, confirmButtonText: 'Cancel order', cancelButtonText: 'Keep order', size: 'small' })],
      rows(1, ['cancel-order']),
    );
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Cancel order' }));
    const dialog = await screen.findByRole('dialog', { name: 'Cancel order ORD-00001?' });

    // Below md the overlay pins the panel to the bottom edge at full width.
    expect(dialog.parentElement?.className).toContain('max-md:items-end');
    expect(dialog.className).toContain('max-md:max-w-none');
    expect(dialog.className).toContain('max-md:rounded-b-none');
    const keep = within(dialog).getByRole('button', { name: 'Keep order' });
    const cancel = within(dialog).getByRole('button', { name: 'Cancel order' });
    // Keep order comes first in the markup and the column reverses, so Cancel order sits on top.
    expect(keep.parentElement?.className).toContain('max-md:flex-col-reverse');
    expect(keep.compareDocumentPosition(cancel) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(keep.className).toContain('max-md:h-11');
    expect(cancel.className).toContain('max-md:h-11');
  });

  it('titles a form by the action name, 520 px wide', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      json({ fields: [{ key: 'note', attribute: 'note', label: 'Note', component: 'text-field', value: null, stacked: false, compact: false, help: null, required: true }], confirmText: null }),
    );
    renderOrders([meta('mark-shipped', 'Mark shipped', { confirmButtonText: 'Mark shipped', size: 'medium' })], rows(1, ['mark-shipped']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Mark shipped' }));

    const dialog = await screen.findByRole('dialog', { name: 'Mark shipped' });
    expect(dialog.className).toContain('max-w-[520px]');
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

    // Filling the field drops its old message.
    fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Fragile' } });
    expect(within(dialog).queryByText('The note field is required.')).toBeNull();
  });

  it("loads a relation field's options from the action lookup route", async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(
        json({
          fields: [{ key: 'target', attribute: 'target', label: 'Target', component: 'belongs-to-field', resource: 'tags', value: null, stacked: false, compact: false, help: null, required: true }],
          confirmText: null,
        }),
      )
      .mockResolvedValueOnce(json({ items: [{ value: 1, display: 'sale' }], has_more: false }));
    renderOrders([meta('merge-tags', 'Merge Tags')], rows(1, ['merge-tags']));
    tick(1);
    fireEvent.click(within(await openMenu()).getByRole('menuitem', { name: 'Merge Tags' }));
    fireEvent.mouseDown(await within(await screen.findByRole('dialog')).findByRole('combobox'), { button: 0 });

    expect(await screen.findByRole('option', { name: 'sale' })).toBeTruthy();
    expect(fetch.mock.calls[1]?.[0]).toBe('/lathe/api/resources/orders/action/associatable?action=merge-tags&field=target&q=&page=1');
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
