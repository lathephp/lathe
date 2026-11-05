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
    uriKey, name, icon: null, description: null, group: null, destructive: false, standalone: false, sole: false, pinned: false,
    withoutConfirmation: true, confirmText: null, confirmButtonText: 'Run Action', cancelButtonText: 'Cancel', size: '2xl', style: 'window',
    asBlob: false, showOnIndex: true, showOnDetail: true, showOnTableRow: false, ...extra,
  };
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });
const ran = { result: { message: 'Action was executed successfully.', level: 'success', effect: null }, permitted: 1, skipped: 0, failed: 0, errors: [] };

describe('the detail action menu', () => {
  it('lists the record actions, disables one the user may not run, and runs on that one record', async () => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json(ran));
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: {
        actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true }), meta('cancel-order', 'Cancel Order', { destructive: true, authorizedToRun: false })],
        pivotActions: [],
        counts: {},
      },
    });

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));
    const menu = screen.getByRole('menu', { name: 'Actions' });
    expect(within(menu).getByRole<HTMLButtonElement>('menuitem', { name: 'Cancel Order' }).disabled).toBe(true);

    fireEvent.click(within(menu).getByRole('menuitem', { name: 'Mark Shipped' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Action was executed successfully.']);
    });
    expect(JSON.parse(fetch.mock.calls[0]?.[1]?.body as string)).toMatchObject({ action: 'mark-shipped', selection: { mode: 'ids', ids: [12], trashed: 'with' } });
  });
});

describe('the detail action menu beside the sidebar', () => {
  // jsdom does no layout: at 768 px with the sidebar open the menu reached 14 px under the sidebar,
  // which covered its edge, so the menu must stack above the sidebar.
  it('stacks above the sidebar', async () => {
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true })], pivotActions: [], counts: {} },
    });

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));
    const layer = (element: Element | null) => Number(/(?:^|\s)z-(\d+)(?:\s|$)/.exec(element?.className ?? '')?.[1] ?? Number.NaN);
    const sidebar = layer(document.querySelector('[data-lathe-sidebar]'));

    expect(sidebar).toBeGreaterThan(0);
    expect(layer(screen.getByRole('menu', { name: 'Actions' }))).toBeGreaterThan(sidebar);
  });
});

describe('the detail action menu at the edge of the content', () => {
  function order() {
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true })], pivotActions: [], counts: {} },
    });
  }

  // jsdom does no layout, so the rects are what a 390 px phone drew: the menu, ending at its button's
  // end edge (217), began 39 px off the screen.
  function drawn(menuLeft: number, menuRight: number) {
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
      const [left, right] = this.getAttribute('role') === 'menu' ? [menuLeft, menuRight] : this.id === 'content' ? [0, 390] : [0, 0];

      return { left, right, top: 0, bottom: 0, x: left, y: 0, width: right - left, height: 0, toJSON: () => ({}) };
    });
  }

  it('opens from its button\'s start edge when, ending at the button\'s end edge, it would run out of the content', async () => {
    order();
    drawn(-39, 217);

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));

    expect(screen.getByRole('menu', { name: 'Actions' }).className).toMatch(/(?:^|\s)start-0(?:\s|$)/);
    expect(screen.getByRole('menu', { name: 'Actions' }).className).not.toMatch(/(?:^|\s)end-0(?:\s|$)/);
  });

  it('ends at its button\'s end edge when it fits there', async () => {
    order();
    drawn(120, 376);

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));

    expect(screen.getByRole('menu', { name: 'Actions' }).className).toMatch(/(?:^|\s)end-0(?:\s|$)/);
  });

  it('closes on Escape while the focus is still on Actions', async () => {
    order();
    const actions = await screen.findByRole('button', { name: 'Actions' });

    fireEvent.click(actions);
    actions.focus();
    fireEvent.keyDown(actions, { key: 'Escape' });

    expect(screen.queryByRole('menu', { name: 'Actions' })).toBeNull();
    expect(document.activeElement).toBe(actions);
  });
});

describe('the detail action menu and its dialog', () => {
  it('gives the focus back to Actions when the dialog an item opened closes', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ fields: [], confirmText: 'Cancel order ORD-12?' }));
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('cancel-order', 'Cancel order', { destructive: true, withoutConfirmation: false, cancelButtonText: 'Keep order', authorizedToRun: true })], pivotActions: [], counts: {} },
    });
    const trigger = await screen.findByRole('button', { name: 'Actions' });
    trigger.focus();
    fireEvent.click(trigger);
    // A click on a button focuses it in a browser, so the item holds the focus as the menu closes.
    const item = within(screen.getByRole('menu', { name: 'Actions' })).getByRole('menuitem', { name: 'Cancel order' });
    item.focus();
    fireEvent.click(item);
    await screen.findByRole('dialog', { name: 'Cancel order ORD-12?' });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Actions' }));
  });
});

describe('inline row actions', () => {
  it('puts the row\'s actions above Delete, which stays the menu\'s last entry', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ actions: [meta('hold-order', 'Hold order', { showOnTableRow: true })], pivotActions: [], counts: {} }));
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false },
          definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
        },
        table: {
          columns: [{ key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' }],
          sortKeys: [],
          rows: [{ id: 7, title: 'ORD-7', cells: { number: { value: 'ORD-7', display: 'ORD-7' } }, abilities: { view: true, delete: true }, actions: ['hold-order'] }],
          rowKey: 'id',
        },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      },
      { url: '/lathe/resources/orders' },
    );

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-7' }));
    await screen.findByRole('menuitem', { name: 'Hold order' });

    expect(screen.getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['View', 'Hold order', 'Delete']);
  });

  it('loads the table-row actions on first open and runs one on its row', async () => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ actions: [meta('hold-order', 'Hold order', { showOnTableRow: true }), meta('flag', 'Flag', { showOnTableRow: true })], pivotActions: [], counts: {} }))
      .mockResolvedValueOnce(json(ran));
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false },
          definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
        },
        table: {
          columns: [{ key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' }],
          sortKeys: [],
          rows: [{ id: 7, title: 'ORD-7', cells: { number: { value: 'ORD-7', display: 'ORD-7' } }, abilities: { view: true }, actions: ['hold-order'] }],
          rowKey: 'id',
        },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      },
      { url: '/lathe/resources/orders' },
    );

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-7' }));
    const item = await screen.findByRole('menuitem', { name: 'Hold order' });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/actions?display=table-row');
    expect(screen.queryByRole('menuitem', { name: 'Flag' })).toBeNull();

    fireEvent.click(item);

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(2);
    });
    expect(JSON.parse(fetch.mock.calls[1]?.[1]?.body as string)).toMatchObject({ action: 'hold-order', selection: { ids: [7] } });
  });
});
