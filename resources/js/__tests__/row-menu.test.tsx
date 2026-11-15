import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { RowMenuPanel } from '../index/lazy';
import { renderPage } from './support';

// The row menu loads its list on the first open; loaded once, it draws on the click itself.
beforeAll(async () => {
  await RowMenuPanel.preload();
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function renderUsers({ create = false } = {}) {
  const row = {
    id: 3,
    title: 'Ada',
    cells: { name: { value: 'Ada', display: 'Ada' } },
    abilities: { view: true, update: true, replicate: true, delete: true, impersonate: true },
  };

  renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'users',
        label: 'Users',
        singularLabel: 'User',
        perPageOptions: [25],
        searchable: false,
        abilities: { create },
        definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
      },
      table: { columns: [{ key: 'name', label: 'Name', component: 'text', sortable: false, sortKey: 'name' }], sortKeys: [], rows: [row], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/users' },
  );
}

describe('a row menu', () => {
  it('starts loading its code on the press, before the click opens it', () => {
    const preload = vi.spyOn(RowMenuPanel, 'preload');
    renderUsers();

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Actions for Ada' }));

    expect(preload).toHaveBeenCalled();
    expect(screen.queryByRole('menu')).toBeNull();
  });

  it('is the kit menu, on the body, and runs its last item', async () => {
    renderUsers();

    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }));
    const menu = await screen.findByRole('menu');

    expect(menu.getAttribute('data-slot')).toBe('dropdown-menu-content');
    expect(within(menu).getAllByRole('menuitem').map((item) => [item.textContent, item.getAttribute('data-slot')])).toEqual(
      ['View', 'Edit', 'Replicate', 'Impersonate', 'Delete'].map((name) => [name, 'dropdown-menu-item']),
    );
    // The table card clips what overflows it, so the menu draws outside it.
    expect(menu.closest('table')).toBeNull();

    fireEvent.click(within(menu).getByRole('menuitem', { name: 'Delete' }));

    expect((await screen.findByRole('alertdialog')).textContent).toContain('Delete Ada?');
  });

  it('moves down with ArrowDown, to the last entry with End, and closes on Escape back onto its button', async () => {
    renderUsers();
    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }), { detail: 0 });
    const menu = await screen.findByRole('menu');
    const item = (name: string) => within(menu).getByRole('menuitem', { name });
    await waitFor(() => {
      expect(document.activeElement).toBe(item('View'));
    });

    fireEvent.keyDown(item('View'), { key: 'ArrowDown' });
    await waitFor(() => {
      expect(document.activeElement).toBe(item('Edit'));
    });
    fireEvent.keyDown(item('Edit'), { key: 'End' });
    await waitFor(() => {
      expect(document.activeElement).toBe(item('Delete'));
    });

    fireEvent.keyDown(item('Delete'), { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('menu')).toBeNull();
    });
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Actions for Ada' }));
  });

  it('keeps a letter typed in it from the page shortcuts', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderUsers({ create: true });
    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }), { detail: 0 });
    const menu = await screen.findByRole('menu');
    await waitFor(() => {
      expect(document.activeElement).toBe(within(menu).getByRole('menuitem', { name: 'View' }));
    });

    // C is the index's shortcut for Create.
    fireEvent.keyDown(document.activeElement ?? menu, { key: 'c' });

    expect(visit).not.toHaveBeenCalled();
  });
});

describe('a row menu near the bottom of the window', () => {
  it('opens above its button when less than its height is left below it', async () => {
    // A 1440x900 window, the button 22 px above its bottom edge, the menu 240 by 200 px.
    vi.spyOn(document.documentElement, 'clientWidth', 'get').mockReturnValue(1440);
    vi.spyOn(document.documentElement, 'clientHeight', 'get').mockReturnValue(900);
    // The positioner around the menu is what is placed; it holds the menu.
    const menuBox = (element: Element) => element.getAttribute('role') === 'menu' || element.querySelector(':scope > [role=menu]') !== null;
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
      const [left, top, width, height] = menuBox(this) ? [0, 0, 240, 200] : this.getAttribute('aria-label') === 'Actions for Ada' ? [1380, 850, 28, 28] : [0, 0, 1440, 900];

      return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height, toJSON: () => ({}) };
    });
    vi.spyOn(HTMLElement.prototype, 'offsetWidth', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 240 : 0;
    });
    vi.spyOn(HTMLElement.prototype, 'offsetHeight', 'get').mockImplementation(function (this: HTMLElement) {
      return menuBox(this) ? 200 : 0;
    });
    renderUsers();

    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }));
    const menu = await screen.findByRole('menu');

    await waitFor(() => {
      expect(menu.getAttribute('data-side')).toBe('top');
    });
  });
});

describe('an open row menu', () => {
  it('closes on a press outside it and gives the focus back to its button', async () => {
    renderUsers();
    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }), { detail: 0 });
    await screen.findByRole('menu');

    fireEvent.pointerDown(document.body);
    fireEvent.mouseDown(document.body);
    fireEvent.click(document.body);

    await waitFor(() => {
      expect(screen.queryByRole('menu')).toBeNull();
    });
  });

  it('stays open on a press inside it', async () => {
    renderUsers();
    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }));
    const menu = await screen.findByRole('menu');

    fireEvent.pointerDown(within(menu).getByRole('menuitem', { name: 'Edit' }));

    expect(screen.queryByRole('menu')).not.toBeNull();
  });
});
