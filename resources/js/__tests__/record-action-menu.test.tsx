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
    expect(within(menu).getByRole('menuitem', { name: 'Cancel Order' }).getAttribute('aria-disabled')).toBe('true');

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

describe('the detail action menu from the keyboard', () => {
  function order() {
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: {
        actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true }), meta('hold-order', 'Hold Order', { authorizedToRun: true }), meta('delete-order', 'Delete', { destructive: true, authorizedToRun: true })],
        pivotActions: [],
        counts: {},
      },
    });
  }

  async function opened() {
    order();
    const trigger = await screen.findByRole('button', { name: 'Actions' });
    trigger.focus();
    fireEvent.click(trigger);
    const menu = await screen.findByRole('menu', { name: 'Actions' });

    return { trigger, menu, item: (name: string) => within(menu).getByRole('menuitem', { name }) };
  }

  it('is the kit menu, with the kit item on each action', async () => {
    const { menu } = await opened();

    expect(menu.getAttribute('data-slot')).toBe('dropdown-menu-content');
    expect(within(menu).getAllByRole('menuitem').map((item) => item.getAttribute('data-slot'))).toEqual(['dropdown-menu-item', 'dropdown-menu-item', 'dropdown-menu-item']);
  });

  // Once Tab had put the focus on the first item, ArrowDown did not move it.
  it('moves down from the first item with ArrowDown and to the last with End', async () => {
    const { item } = await opened();
    item('Mark Shipped').focus();

    fireEvent.keyDown(item('Mark Shipped'), { key: 'ArrowDown' });
    await waitFor(() => {
      expect(document.activeElement).toBe(item('Hold Order'));
    });

    fireEvent.keyDown(item('Hold Order'), { key: 'End' });
    await waitFor(() => {
      expect(document.activeElement).toBe(item('Delete'));
    });
  });

  it('focuses the item whose name starts with the letter typed', async () => {
    const { item } = await opened();
    item('Mark Shipped').focus();

    fireEvent.keyDown(item('Mark Shipped'), { key: 'd' });

    await waitFor(() => {
      expect(document.activeElement).toBe(item('Delete'));
    });
  });

  it('closes on Escape and gives the focus back to Actions', async () => {
    const { trigger, item } = await opened();
    item('Hold Order').focus();

    fireEvent.keyDown(item('Hold Order'), { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('menu', { name: 'Actions' })).toBeNull();
    });
    expect(document.activeElement).toBe(trigger);
  });

  // A menu inside the content stacked under the sidebar. On the body, it stacks above it.
  it('draws its menu on the body, outside the content', async () => {
    const { menu } = await opened();

    expect(menu.closest('#content')).toBeNull();
    expect(menu.closest('[data-lathe-sidebar]')).toBeNull();
  });
});

describe('the detail action menu beside the open sidebar', () => {
  // The walk at 768 px: the content starts at 256 and the menu, 256 wide and ending at its button's
  // end edge (496), began at 240, over the sidebar's edge. It moves into the content instead.
  it('stays wholly inside the content, 8 px in from its edge', async () => {
    const menuBox = (element: Element) => element.getAttribute('role') === 'menu' || element.querySelector(':scope > [role=menu]') !== null;
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
      const [left, top, width, height] = menuBox(this)
        ? [0, 0, 256, 120]
        : this.id === 'content'
          ? [256, 0, 512, 900]
          : this.textContent === 'Actions' && this.tagName === 'BUTTON'
            ? [420, 100, 76, 28]
            : [0, 0, 768, 900];

      return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height, toJSON: () => ({}) };
    });
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 256 : 0;
    });
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 120 : 0;
    });
    // A boundary element is measured by its inner box.
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockImplementation(function (this: Element) {
      return this === document.documentElement ? 768 : this.id === 'content' ? 512 : 0;
    });
    vi.spyOn(Element.prototype, 'clientHeight', 'get').mockImplementation(function (this: Element) {
      return this === document.documentElement || this.id === 'content' ? 900 : 0;
    });
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true })], pivotActions: [], counts: {} },
    });

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));
    const positioner = (await screen.findByRole('menu', { name: 'Actions' })).parentElement as HTMLElement;

    await waitFor(() => {
      // The positioner places the menu with a translate from the page's top start corner.
      expect(Number.parseFloat(/translate\(([-\d.]+)px/.exec(positioner.style.transform)?.[1] ?? 'NaN')).toBeGreaterThanOrEqual(264);
    });
  });
});

describe('the detail action menu on a phone', () => {
  // At 390 px the Actions button ends 4 px from the window's end edge; the menu, ending there, moves in to 8 px.
  it('keeps its end edge 8 px inside the window', async () => {
    const menuBox = (element: Element) => element.getAttribute('role') === 'menu' || element.querySelector(':scope > [role=menu]') !== null;
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
      const [left, top, width, height] = menuBox(this)
        ? [0, 0, 256, 120]
        : this.id === 'content'
          ? [0, 0, 390, 844]
          : this.textContent === 'Actions' && this.tagName === 'BUTTON'
            ? [310, 100, 76, 28]
            : [0, 0, 390, 844];

      return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height, toJSON: () => ({}) };
    });
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 256 : 0;
    });
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 120 : 0;
    });
    vi.spyOn(Element.prototype, 'clientWidth', 'get').mockImplementation(function (this: Element) {
      return this === document.documentElement || this.id === 'content' ? 390 : 0;
    });
    vi.spyOn(Element.prototype, 'clientHeight', 'get').mockImplementation(function (this: Element) {
      return this === document.documentElement || this.id === 'content' ? 844 : 0;
    });
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('mark-shipped', 'Mark Shipped', { authorizedToRun: true })], pivotActions: [], counts: {} },
    });

    fireEvent.click(await screen.findByRole('button', { name: 'Actions' }));
    const positioner = (await screen.findByRole('menu', { name: 'Actions' })).parentElement as HTMLElement;

    await waitFor(() => {
      const start = Number.parseFloat(/translate\(([-\d.]+)px/.exec(positioner.style.transform)?.[1] ?? 'NaN');
      expect(start + 256).toBeLessThanOrEqual(390 - 8);
    });
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

describe('the detail action menu and its dialog, the second time', () => {
  // The first run loads the dialog's code, so the dialog mounts later; once loaded, it mounts as the
  // menu closes, while the item still holds the focus.
  it('keeps the focus in the dialog and gives it back to Actions when it closes', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(json({ fields: [], confirmText: 'Cancel order ORD-12?' })));
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: false } },
      record: { id: 12, title: 'ORD-12', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true }, trashed: false },
      breadcrumbs: [],
      actions: { actions: [meta('cancel-order', 'Cancel order', { destructive: true, withoutConfirmation: false, cancelButtonText: 'Keep order', authorizedToRun: true })], pivotActions: [], counts: {} },
    });
    const trigger = await screen.findByRole('button', { name: 'Actions' });

    for (let run = 1; run <= 2; run++) {
      trigger.focus();
      fireEvent.click(trigger);
      const item = within(await screen.findByRole('menu', { name: 'Actions' })).getByRole('menuitem', { name: 'Cancel order' });
      item.focus();
      fireEvent.click(item);
      const dialog = await screen.findByRole('dialog', { name: 'Cancel order ORD-12?' });
      await waitFor(() => {
        expect(dialog.contains(document.activeElement)).toBe(true);
      });

      fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

      await waitFor(() => {
        expect(screen.queryByRole('dialog')).toBeNull();
      });
      await waitFor(() => {
        expect(document.activeElement).toBe(trigger);
      });
    }
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
