import { fireEvent, screen, within } from '@testing-library/react';
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

function rect(left: number, top: number, width: number, height: number): DOMRect {
  return { x: left, y: top, left, top, width, height, right: left + width, bottom: top + height, toJSON: () => ({}) };
}

function renderUsers() {
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
        abilities: { create: false },
        definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
      },
      table: { columns: [{ key: 'name', label: 'Name', component: 'text', sortable: false, sortKey: 'name' }], sortKeys: [], rows: [row], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/users' },
  );
}

describe('a row menu near the bottom of the window', () => {
  it('opens above its button, fixed to the window, and runs its last item', () => {
    // A 1440x900 window, the button 22 px above its bottom edge, the menu 200 by 240 px.
    vi.stubGlobal('innerWidth', 1440);
    vi.stubGlobal('innerHeight', 900);
    vi.spyOn(Element.prototype, 'getBoundingClientRect').mockImplementation(function (this: Element) {
      if (this.getAttribute('role') === 'menu') {
        return rect(0, 0, 240, 200);
      }

      return this.getAttribute('aria-label') === 'Actions for Ada' ? rect(1380, 850, 28, 28) : rect(0, 0, 0, 0);
    });
    renderUsers();

    fireEvent.click(screen.getByRole('button', { name: 'Actions for Ada' }));
    const menu = screen.getByRole('menu');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['View', 'Edit', 'Replicate', 'Impersonate', 'Delete']);
    expect([menu.style.position, menu.style.top, menu.style.bottom, menu.style.left]).toEqual(['fixed', '', '54px', '1168px']);

    fireEvent.click(within(menu).getByRole('menuitem', { name: 'Delete' }));

    expect(screen.getByRole('alertdialog').textContent).toContain('Delete Ada?');
  });
});
