import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import type { SharedProps } from '../types';
import { renderPage } from './support';
import { RowMenuPanel } from '../index/lazy';

// The row menu loads its list on the first open; loaded once, it draws on the click itself.
beforeAll(async () => {
  await RowMenuPanel.preload();
});

afterEach(() => {
  vi.restoreAllMocks();
});

const columns = [
  { key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' },
  { key: 'total', label: 'Total', component: 'text', sortable: false, sortKey: 'total' },
];

interface TestRow {
  id: number;
  title: string;
  cells: Record<string, { value: unknown; display: string }>;
  abilities: Record<string, boolean>;
  trashed?: boolean;
}

function row(id: number, abilities: Record<string, boolean> = { view: true }, trashed?: boolean): TestRow {
  const number = `ORD-${String(id).padStart(5, '0')}`;

  return {
    id,
    title: number,
    cells: { number: { value: number, display: number }, total: { value: id, display: `$${String(id)}.00` } },
    abilities,
    ...(trashed === undefined ? {} : { trashed }),
  };
}

function rows(count: number, abilities?: Record<string, boolean>) {
  return Array.from({ length: count }, (_, index) => row(index + 1, abilities));
}

interface Options {
  definition?: Record<string, unknown>;
  rows?: TestRow[];
  tableStyle?: SharedProps['tableStyle'];
}

function renderOrders(options: Options = {}) {
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
          ...options.definition,
        },
      },
      table: { columns, sortKeys: [], rows: options.rows ?? rows(25), rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: true, sort: '' },
      count: { value: 10000, exact: false, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders', shared: options.tableStyle === undefined ? {} : { tableStyle: options.tableStyle } },
  );
}

function bodyRows(): HTMLElement[] {
  const [, body] = screen.getAllByRole('rowgroup');

  return within(body as HTMLElement).queryAllByRole('row');
}

function rowOf(title: string): HTMLElement {
  const found = bodyRows().find((element) => element.textContent.includes(title));

  if (found === undefined) {
    throw new Error(`No row for ${title}.`);
  }

  return found;
}

function clickCell(title: string, init: MouseEventInit = {}) {
  fireEvent.click(within(rowOf(title)).getByText('$12.00'), init);
}

describe('row click', () => {
  it('opens the detail page for detail', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders();

    clickCell('ORD-00012');

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/12');
  });

  it('opens the edit page for edit when the user may update', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ definition: { clickAction: 'edit' }, rows: rows(25, { view: true, update: true }) });

    clickCell('ORD-00012');

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/12/edit');
  });

  it('falls back to the detail page for edit without update', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ definition: { clickAction: 'edit' }, rows: rows(25, { view: true, update: false }) });

    clickCell('ORD-00012');

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/12');
  });

  it('opens detail in a new tab on Cmd-click', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    renderOrders();

    clickCell('ORD-00012', { metaKey: true });

    expect(open.mock.calls[0]?.slice(0, 2)).toEqual(['/lathe/resources/orders/12', '_blank']);
    expect(visit).not.toHaveBeenCalled();
  });

  it('opens edit in a new tab on Ctrl-click', () => {
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    renderOrders({ definition: { clickAction: 'edit' }, rows: rows(25, { view: true, update: true }) });

    clickCell('ORD-00012', { ctrlKey: true });

    expect(open.mock.calls[0]?.slice(0, 2)).toEqual(['/lathe/resources/orders/12/edit', '_blank']);
  });

  it('toggles the row checkbox for select', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ definition: { clickAction: 'select' } });

    const checkbox = screen.getByRole<HTMLInputElement>('checkbox', { name: 'Select ORD-00012' });

    clickCell('ORD-00012');
    expect(checkbox.checked).toBe(true);

    clickCell('ORD-00012');
    expect(checkbox.checked).toBe(false);
    expect(visit).not.toHaveBeenCalled();
  });

  it('does nothing for ignore', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const open = vi.spyOn(window, 'open').mockImplementation(() => null);
    renderOrders({ definition: { clickAction: 'ignore' } });

    clickCell('ORD-00012');
    clickCell('ORD-00012', { metaKey: true });

    expect(visit).not.toHaveBeenCalled();
    expect(open).not.toHaveBeenCalled();
    expect(screen.getByRole<HTMLInputElement>('checkbox', { name: 'Select ORD-00012' }).checked).toBe(false);
  });

  it('ticks the row without opening it when the checkbox is clicked', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders();

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select ORD-00012' }));

    expect(screen.getByRole<HTMLInputElement>('checkbox', { name: 'Select ORD-00012' }).checked).toBe(true);
    expect(visit).not.toHaveBeenCalled();
  });
});

describe('row selection', () => {
  it('ticks the 25 rows of the page from the header checkbox', () => {
    renderOrders();

    const all = screen.getByRole<HTMLInputElement>('checkbox', { name: 'Select all' });
    fireEvent.click(all);

    const ticked = screen.getAllByRole<HTMLInputElement>('checkbox', { name: /^Select ORD-/ }).filter((box) => box.checked);
    expect(ticked).toHaveLength(25);
    expect(all.checked).toBe(true);

    fireEvent.click(all);

    expect(screen.getAllByRole<HTMLInputElement>('checkbox', { name: /^Select ORD-/ }).filter((box) => box.checked)).toHaveLength(0);
  });

  it('shows the header checkbox as mixed while some rows are ticked', () => {
    renderOrders();

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select ORD-00003' }));

    const all = screen.getByRole<HTMLInputElement>('checkbox', { name: 'Select all' });
    expect(all.indeterminate).toBe(true);
    expect(all.checked).toBe(false);
  });
});

function menuItems(title: string): string[] {
  fireEvent.click(screen.getByRole('button', { name: `Actions for ${title}` }));

  return screen.getAllByRole('menuitem').map((item) => item.textContent);
}

describe('row hover actions', () => {
  it('shows View and Edit links that appear on row hover or focus', () => {
    renderOrders({ rows: [row(1, { view: true, update: true })] });

    const view = screen.getByRole('link', { name: 'View ORD-00001' });
    const edit = screen.getByRole('link', { name: 'Edit ORD-00001' });

    expect(view.getAttribute('href')).toBe('/lathe/resources/orders/1');
    expect(edit.getAttribute('href')).toBe('/lathe/resources/orders/1/edit');
    for (const link of [view, edit]) {
      expect(link.className).toContain('opacity-0');
      expect(link.className).toContain('group-hover:opacity-100');
      expect(link.className).toContain('group-focus-within:opacity-100');
    }
  });

  it('leaves out Edit on a row the user may not update', () => {
    renderOrders({ rows: [row(1, { view: true })] });

    expect(screen.getByRole('link', { name: 'View ORD-00001' })).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Edit ORD-00001' })).toBeNull();
  });

  it('visits once when View is clicked, not again through the row', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ rows: [row(1, { view: true, update: true })] });

    fireEvent.click(screen.getByRole('link', { name: 'View ORD-00001' }));

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/1');
  });

  it('ends the row menu with Delete after a separator, below Impersonate', () => {
    renderOrders({ rows: [row(1, { view: true, update: true, delete: true, impersonate: true })] });

    expect(menuItems('ORD-00001')).toEqual(['View', 'Edit', 'Impersonate', 'Delete']);
    const delet = screen.getByRole('menuitem', { name: 'Delete' });
    expect(delet.previousElementSibling?.getAttribute('role')).toBe('separator');
  });
});

describe('row menu', () => {

  it('offers Delete on a live row the user may delete', () => {
    renderOrders({ rows: [row(1, { view: true, delete: true, restore: true, forceDelete: true }, false)] });

    expect(menuItems('ORD-00001')).toEqual(['View', 'Delete']);
  });

  it('offers Restore and Force Delete on a trashed row', () => {
    renderOrders({ rows: [row(1, { view: true, delete: true, restore: true, forceDelete: true }, true)] });

    expect(menuItems('ORD-00001')).toEqual(['View', 'Restore', 'Force Delete']);
  });

  it('offers only what the trashed row allows', () => {
    renderOrders({ rows: [row(1, { view: true, restore: false, forceDelete: true }, true), row(2, { view: true, restore: true, forceDelete: false }, true)] });

    expect(menuItems('ORD-00001')).toEqual(['View', 'Force Delete']);
    fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
    expect(menuItems('ORD-00002')).toEqual(['View', 'Restore']);
  });

  it('offers View and Edit, without Delete, to a user who may not delete', () => {
    renderOrders({ rows: [row(1, { view: true, update: true, delete: false })] });

    expect(menuItems('ORD-00001')).toEqual(['View', 'Edit']);
    expect(screen.getByRole('menuitem', { name: 'Edit' }).getAttribute('href')).toBe('/lathe/resources/orders/1/edit');
  });

  it('has no menu for a row the user may do nothing with', () => {
    renderOrders({ rows: [row(1, {})] });

    expect(screen.queryByRole('button', { name: 'Actions for ORD-00001' })).toBeNull();
  });

  it('deletes after the confirm, reloads the table and count and shows a toast', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json({ redirect: 'orders' }));
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ rows: [row(12, { view: true, delete: true })] });

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));

    expect(fetch).not.toHaveBeenCalled();
    const dialog = screen.getByRole('alertdialog');
    expect(dialog.textContent).toContain('Delete ORD-00012?');

    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith({ only: ['table', 'count', 'tabCounts', 'summaries'] });
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/12');
    expect(fetch.mock.calls[0]?.[1]).toMatchObject({ method: 'DELETE' });
    expect(__panel.toasts.get()).toMatchObject([{ type: 'success', message: 'ORD-00012 was deleted.' }]);
    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(visit).not.toHaveBeenCalled();
  });

  it('focuses the confirm, closes it on Escape and gives the focus back to the row menu button', () => {
    renderOrders({ rows: [row(12, { view: true, delete: true })] });
    const trigger = screen.getByRole('button', { name: 'Actions for ORD-00012' });
    trigger.focus();
    fireEvent.click(trigger);
    const item = screen.getByRole('menuitem', { name: 'Delete' });
    item.focus();
    fireEvent.click(item);
    const dialog = screen.getByRole('alertdialog', { name: 'Delete ORD-00012?' });

    expect(dialog.contains(document.activeElement)).toBe(true);

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
  });

  it('opens the confirm on the body, outside the table, so its scrim covers the top bar', () => {
    renderOrders({ rows: [row(12, { view: true, delete: true })] });
    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));

    expect(screen.getByRole('alertdialog').closest('table')).toBeNull();
    expect(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Delete' }).className).toContain('lathe-button-size-default');
  });

  it('sends nothing when the confirm is cancelled', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    renderOrders({ rows: [row(12, { view: true, delete: true })] });

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Cancel' }));

    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('restores a trashed row', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json({ redirect: 'orders' }));
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ rows: [row(12, { view: true, restore: true }, true)] });

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Restore' }));

    await waitFor(() => {
      expect(reload).toHaveBeenCalledWith({ only: ['table', 'count', 'tabCounts', 'summaries'] });
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/12/restore');
    expect(fetch.mock.calls[0]?.[1]).toMatchObject({ method: 'PUT' });
    expect(__panel.toasts.get()).toMatchObject([{ type: 'success', message: 'ORD-00012 was restored.' }]);
  });

  it('force deletes a trashed row after the confirm', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json({ redirect: 'orders' }));
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ rows: [row(12, { view: true, forceDelete: true }, true)] });

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Force Delete' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Force Delete' }));

    await waitFor(() => {
      expect(__panel.toasts.get()).toMatchObject([{ type: 'success', message: 'ORD-00012 was deleted for good.' }]);
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders/12/force');
    expect(fetch.mock.calls[0]?.[1]).toMatchObject({ method: 'DELETE' });
  });

  it('shows an error toast when the delete fails and keeps the row', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(Response.json({ message: 'Server Error' }, { status: 500 }));
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ rows: [row(12, { view: true, delete: true })] });

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Delete' }));

    await waitFor(() => {
      expect(__panel.toasts.get()).toMatchObject([{ type: 'error', message: 'ORD-00012 could not be deleted.' }]);
    });
    expect(reload).not.toHaveBeenCalled();
    expect(bodyRows()).toHaveLength(1);
  });
});

describe('soft deleted rows', () => {
  it('marks a trashed row with a Soft Deleted badge', () => {
    renderOrders({ rows: [row(1, { view: true }, true), row(2, { view: true }, false)] });

    expect(within(rowOf('ORD-00001')).getByText('Soft Deleted')).toBeTruthy();
    expect(within(rowOf('ORD-00002')).queryByText('Soft Deleted')).toBeNull();
  });
});

describe('table visuals', () => {
  it('forces 32 px rows for the tight style', () => {
    renderOrders({ definition: { tableStyle: 'tight' }, tableStyle: { density: 'spacious', striped: false } });

    expect(bodyRows().every((element) => element.classList.contains('h-8') && !element.classList.contains('h-12'))).toBe(true);
  });

  it('paints the stuck cells in the colour of their row, so the header and a hovered row read edge to edge', () => {
    renderOrders({ rows: [row(1, { view: true, update: true })] });

    const cells = [...screen.getAllByRole('columnheader'), ...within(bodyRows()[0] as HTMLElement).getAllByRole('cell')];
    const stuck = cells.filter((cell) => cell.classList.contains('sticky'));

    expect(stuck.length).toBe(6);
    expect(stuck.every((cell) => cell.classList.contains('bg-inherit'))).toBe(true);
  });

  it('draws vertical borders on rows and header with showColumnBorders', () => {
    const { unmount } = renderOrders({ definition: { showColumnBorders: true } });

    expect(screen.getAllByRole('columnheader').every((cell) => cell.classList.contains('border-e'))).toBe(true);
    expect(within(bodyRows()[0] as HTMLElement).getAllByRole('cell').every((cell) => cell.classList.contains('border-e'))).toBe(true);
    unmount();

    renderOrders();

    expect(screen.getAllByRole('columnheader').some((cell) => cell.classList.contains('border-e'))).toBe(false);
  });
});
