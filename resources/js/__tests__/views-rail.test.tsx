import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

interface SavedTab {
  id: number;
  name: string;
  badgeType: string | null;
  showCount: boolean;
  stale: boolean;
  filters: Record<string, unknown>;
  sort: string | null;
}

const paid: SavedTab = { id: 7, name: 'Paid', badgeType: 'success', showCount: true, stale: false, filters: { status: 'paid' }, sort: null };
const cancelled: SavedTab = { id: 8, name: 'Cancelled', badgeType: 'danger', showCount: false, stale: false, filters: { status: 'cancelled' }, sort: null };

const capped = { value: 10000, exact: false, capped: true, estimated: false };

interface Options {
  url?: string;
  saved?: SavedTab[];
  tabCounts?: Record<string, unknown>;
  /** Which developer tabs load their badge with the counts. */
  deferred?: string[];
  lenses?: { uriKey: string; name: string }[];
}

function renderOrders(options: Options = {}) {
  const props: Record<string, unknown> = {
    resource: {
      uriKey: 'orders',
      label: 'Orders',
      singularLabel: 'Order',
      perPageOptions: [25, 50, 100],
      searchable: false,
      abilities: { create: false },
      definition: { debounce: 0.5, savedTabs: true },
    },
    table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    count: null,
    tabs: {
      items: [
        { key: 'all', name: 'All', icon: null, badge: null, badgeType: null, deferredBadge: options.deferred?.includes('all') ?? false },
        { key: 'pending', name: 'Pending', icon: null, badge: null, badgeType: 'warning', deferredBadge: options.deferred?.includes('pending') ?? false },
        { key: 'paid', name: 'Paid', icon: null, badge: null, badgeType: null, deferredBadge: options.deferred?.includes('paid') ?? false },
      ],
      active: 'pending',
      saved: options.saved ?? [paid, cancelled],
      activeSaved: null,
    },
    lenses: options.lenses ?? [],
  };

  if (options.tabCounts !== undefined) {
    props.tabCounts = options.tabCounts;
  }

  return renderPage('Resources/Index', props, { url: options.url ?? '/lathe/resources/orders?tab=pending' });
}

/** The rail beside the card. While the Views menu is open its popover holds a second list. */
function rail(): HTMLElement {
  return screen.getAllByRole('navigation', { name: 'Views' })[0] as HTMLElement;
}

/** The tab buttons in a list, by their text, in order. */
function tabTexts(list: HTMLElement = rail()): string[] {
  return Array.from(list.querySelectorAll('[data-tab]'), (tab) => tab.textContent);
}

function savedTab(name: string, list: HTMLElement = rail()): HTMLElement {
  return Array.from(list.querySelectorAll<HTMLElement>('[data-tab^="saved:"]')).find((tab) => tab.textContent.startsWith(name)) as HTMLElement;
}

interface Call {
  url: string;
  method: string;
  body: unknown;
}

let calls: Call[] = [];

/** Answer every request with `body` and `status`, and keep what was sent. */
function answer(status = 200, body: unknown = {}) {
  calls = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init: RequestInit) => {
      calls.push({ url, method: init.method ?? 'GET', body: init.body === undefined ? undefined : JSON.parse(init.body as string) });

      return Promise.resolve({ status, ok: status < 400, text: () => Promise.resolve(status === 204 ? '' : JSON.stringify(body)) });
    }),
  );
}

function mockReload() {
  return vi.spyOn(router, 'reload').mockImplementation(() => undefined);
}

describe('views rail', () => {
  it('lists the tabs with their counts, then the saved views, then the lenses with none, 220 px wide', () => {
    const lenses = [{ uriKey: 'pending-orders', name: 'Pending Orders' }];
    const { unmount } = renderOrders({ deferred: ['all', 'pending', 'paid'], lenses });

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    expect(rail().querySelectorAll('[data-badge-placeholder]')).toHaveLength(4);
    unmount();

    const exact = (value: number) => ({ count: { value, exact: true, capped: false, estimated: false } });
    renderOrders({ deferred: ['all', 'pending', 'paid'], lenses, tabCounts: { all: exact(10000), pending: exact(2424), paid: exact(2552) } });
    const entries = Array.from(rail().querySelectorAll('[data-tab], a'), (entry) => entry.textContent);

    expect(entries).toEqual(['All10,000', 'Pending2,424', 'Paid2,552', 'Paid', 'Cancelled', 'Pending Orders']);
    const lens = within(rail()).getByRole('link', { name: 'Pending Orders' });
    expect(lens.getAttribute('href')).toBe('/lathe/resources/orders/lens/pending-orders');
    expect(lens.querySelector('.lathe-tab-count, [data-badge-placeholder]')).toBeNull();
    expect(rail().className).toContain('w-[220px]');
  });

  it('sits beside the card, which draws no tab band above its toolbar', () => {
    renderOrders();
    const card = document.querySelector('[data-lathe-card]') as HTMLElement;

    expect(card.querySelector('nav')).toBeNull();
    expect(card.contains(rail())).toBe(false);
    expect(card.firstElementChild?.hasAttribute('data-lathe-toolbar')).toBe(true);
  });

  it('shows developer tabs, then saved tabs, with badges once the counts arrive', () => {
    const { unmount } = renderOrders();

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    unmount();

    renderOrders({ tabCounts: { pending: { count: capped }, 'saved:7': { count: { value: 1234, exact: true, capped: false, estimated: false } } } });

    expect(tabTexts()).toEqual(['All', 'Pending10,000+', 'Paid', 'Paid1,234', 'Cancelled']);
  });

  it('draws each count as a pill in its tone', () => {
    renderOrders({ tabCounts: { pending: { count: capped }, 'saved:7': { count: capped } } });
    const pill = (name: string) => rail().querySelector(`[data-tab="${name}"] .lathe-tab-count`);

    expect(pill('pending')?.getAttribute('data-badge-type')).toBe('warning');
    expect(pill('saved:7')?.getAttribute('data-badge-type')).toBe('success');
    expect(rail().querySelector('[data-tab="all"]')?.className).toBe('lathe-tab');
  });

  it('holds a placeholder badge in each counting tab until the counts arrive', () => {
    const { unmount } = renderOrders({ deferred: ['pending', 'paid'] });
    const placeholders = (name: string) => Array.from(rail().querySelectorAll(`[data-tab="${name}"] [data-badge-placeholder]`));

    // Pending and Paid count, All does not; of the saved tabs only Paid shows its count.
    expect(placeholders('all')).toHaveLength(0);
    expect(placeholders('pending')).toHaveLength(1);
    expect(placeholders('paid')).toHaveLength(1);
    expect(placeholders('saved:7')).toHaveLength(1);
    expect(placeholders('saved:8')).toHaveLength(0);
    expect(placeholders('pending')[0]?.getAttribute('aria-hidden')).toBe('true');
    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    unmount();

    renderOrders({ deferred: ['pending', 'paid'], tabCounts: { pending: { count: capped }, paid: { count: capped }, 'saved:7': { count: capped } } });

    expect(document.querySelectorAll('[data-badge-placeholder]')).toHaveLength(0);
    expect(tabTexts()).toEqual(['All', 'Pending10,000+', 'Paid10,000+', 'Paid10,000+', 'Cancelled']);
  });

  it('gives a count the width its placeholder held, so the views after it stay put', () => {
    const { unmount } = renderOrders({ deferred: ['pending'] });
    const placeholder = rail().querySelector('[data-tab="pending"] [data-badge-placeholder]');
    expect(placeholder?.className).toContain('w-12');
    unmount();

    renderOrders({ deferred: ['pending'], tabCounts: { pending: { count: { value: 2424, capped: false } } } });

    expect(rail().querySelector('[data-tab="pending"] span:last-child')?.className).toContain('min-w-12');
  });

  it('marks the current tab as the current page', () => {
    renderOrders();

    expect(within(rail()).getByRole('button', { name: 'Pending' }).getAttribute('aria-current')).toBe('page');
    expect(within(rail()).getByRole('button', { name: 'All' }).getAttribute('aria-current')).toBeNull();
  });

  it('clears tab when the first tab is chosen and keeps the filters', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ url: '/lathe/resources/orders?tab=pending&filter[status]=paid' });

    fireEvent.click(within(rail()).getByRole('button', { name: 'All' }));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ filter: { status: 'paid' } });
  });

  it('opens a saved tab as saved, without the filters, search and sort on screen', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ url: '/lathe/resources/orders?tab=pending&filter[status]=cancelled&sort=-total_cents&q=x&trashed=with&group=status' });

    fireEvent.click(savedTab('Paid'));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ saved_tab: '7' });
  });

  it('draws nothing, rail or Views button, with no tabs, no lenses and saved tabs off', () => {
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders',
          label: 'Orders',
          singularLabel: 'Order',
          perPageOptions: [25],
          searchable: false,
          abilities: { create: false },
          definition: { debounce: 0.5, savedTabs: false },
        },
        table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
        count: null,
        lenses: [],
      },
      { url: '/lathe/resources/orders' },
    );

    expect(screen.queryByRole('navigation', { name: 'Views' })).toBeNull();
    expect(screen.queryByRole('button', { name: /^Views/ })).toBeNull();
    expect(document.querySelector('.lathe-views-rail')).toBeNull();
  });
});

describe('views menu', () => {
  it('opens the same list in a popover from the toolbar, named for the current view', () => {
    renderOrders();
    const button = screen.getByRole('button', { name: 'Views Pending' });

    expect(button.closest('[data-lathe-toolbar]')).not.toBeNull();
    expect(button.getAttribute('aria-expanded')).toBe('false');

    fireEvent.click(button);
    const popover = screen.getByRole('dialog', { name: 'Views' });

    expect(button.getAttribute('aria-expanded')).toBe('true');
    expect(tabTexts(within(popover).getByRole('navigation', { name: 'Views' }))).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    expect(within(popover).getByRole('button', { name: 'Save view' })).toBeTruthy();
  });

  it('closes on Escape and gives the focus back to the button', () => {
    renderOrders();
    const button = screen.getByRole('button', { name: 'Views Pending' });
    fireEvent.click(button);
    const popover = screen.getByRole('dialog', { name: 'Views' });

    fireEvent.keyDown(within(popover).getByRole('button', { name: 'All' }), { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Views' })).toBeNull();
    expect(document.activeElement).toBe(button);
  });

  it('closes on a press outside it', () => {
    renderOrders();
    fireEvent.click(screen.getByRole('button', { name: 'Views Pending' }));

    fireEvent.pointerDown(document.body);

    expect(screen.queryByRole('dialog', { name: 'Views' })).toBeNull();
  });

  it('renames a saved view from the popover, whose menu Escape closes alone', async () => {
    answer(200, { id: 7 });
    const reload = mockReload();
    renderOrders();
    fireEvent.click(screen.getByRole('button', { name: 'Views Pending' }));
    const popover = screen.getByRole('dialog', { name: 'Views' });

    fireEvent.click(within(popover).getByRole('button', { name: 'Options for Paid' }));
    fireEvent.keyDown(within(popover).getByRole('menu'), { key: 'Escape' });

    expect(within(popover).queryByRole('menu')).toBeNull();
    expect(screen.getByRole('dialog', { name: 'Views' })).toBe(popover);

    fireEvent.click(within(popover).getByRole('button', { name: 'Options for Paid' }));
    fireEvent.click(within(popover).getByRole('menuitem', { name: 'Rename' }));
    const dialog = screen.getByRole('dialog', { name: 'Rename view' });
    fireEvent.change(within(dialog).getByLabelText('Name'), { target: { value: 'Settled' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/7', method: 'PUT', body: { name: 'Settled' } }]);
  });
});

describe('saving a view', () => {
  it('posts the current filters, search, sort and trashed with the name, count switch and badge colour', async () => {
    answer(201, { id: 9 });
    const reload = mockReload();
    renderOrders({ url: '/lathe/resources/orders?filter[status]=paid&q=ann&sort=-total_cents&trashed=with' });

    fireEvent.click(within(rail()).getByRole('button', { name: 'Save view' }));
    const dialog = screen.getByRole('dialog', { name: 'Save view' });
    fireEvent.change(within(dialog).getByLabelText('Name'), { target: { value: 'Big paid' } });
    fireEvent.click(within(dialog).getByRole('switch', { name: 'Show count' }));
    fireEvent.click(within(dialog).getByRole('radio', { name: 'Danger' }));
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([
      {
        url: '/lathe/api/resources/orders/saved-tabs',
        method: 'POST',
        body: { name: 'Big paid', filters: { status: 'paid' }, q: 'ann', sort: '-total_cents', trashed: 'with', show_count: true, badge_type: 'danger' },
      },
    ]);
    expect(reload.mock.calls[0]?.[0]).toMatchObject({ only: ['tabs', 'tabCounts'] });
    expect(screen.queryByRole('dialog', { name: 'Save view' })).toBeNull();
  });
});

describe('saved tab menu', () => {
  it('renames a saved tab', async () => {
    answer(200, { id: 7 });
    const reload = mockReload();
    renderOrders();

    fireEvent.click(within(rail()).getByRole('button', { name: 'Options for Paid' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Rename' }));
    const dialog = screen.getByRole('dialog', { name: 'Rename view' });

    expect(within(dialog).getByLabelText<HTMLInputElement>('Name').value).toBe('Paid');

    fireEvent.change(within(dialog).getByLabelText('Name'), { target: { value: 'Settled' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/7', method: 'PUT', body: { name: 'Settled' } }]);
  });

  it('deletes a saved tab only after the confirm', async () => {
    answer(204);
    const reload = mockReload();
    renderOrders();

    fireEvent.click(within(rail()).getByRole('button', { name: 'Options for Cancelled' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }));

    expect(calls).toEqual([]);

    fireEvent.click(within(screen.getByRole('alertdialog', { name: 'Delete Cancelled?' })).getByRole('button', { name: 'Delete' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/8', method: 'DELETE', body: undefined }]);
  });
});

describe('reordering saved tabs', () => {
  it('moves a focused view down on Alt+Down, saves the order and announces it', async () => {
    answer(204);
    const reload = mockReload();
    renderOrders();

    expect(savedTab('Paid').getAttribute('aria-keyshortcuts')).toBe('Alt+ArrowUp Alt+ArrowDown');

    fireEvent.keyDown(savedTab('Paid'), { key: 'ArrowDown', altKey: true });

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Cancelled', 'Paid']);
    expect(screen.getByText('Moved Paid to position 2').getAttribute('aria-live')).toBe('polite');
    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/reorder', method: 'PUT', body: { ids: [8, 7] } }]);
  });

  it('moves a view by dragging its handle onto another', async () => {
    answer(204);
    const reload = mockReload();
    renderOrders();
    const handle = savedTab('Paid').parentElement?.querySelector('[draggable]') as HTMLElement;
    const target = savedTab('Cancelled').closest('li') as HTMLElement;
    const dataTransfer = { setData: vi.fn(), effectAllowed: '' };

    fireEvent.dragStart(handle, { dataTransfer });
    fireEvent.dragOver(target, { dataTransfer });
    fireEvent.drop(target, { dataTransfer });

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Cancelled', 'Paid']);
    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/reorder', method: 'PUT', body: { ids: [8, 7] } }]);
  });

  it('puts the old order back when the server answers 422', async () => {
    answer(422, { message: 'Send every one of your saved tabs once', errors: { ids: ['Send every one of your saved tabs once'] } });
    const reload = mockReload();
    renderOrders();

    fireEvent.keyDown(savedTab('Cancelled'), { key: 'ArrowUp', altKey: true });

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Cancelled', 'Paid']);
    await vi.waitFor(() => {
      expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    });
    expect(reload).not.toHaveBeenCalled();
    expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Send every one of your saved tabs once']);
  });
});

describe('stale saved tab', () => {
  it('shows View updated and Reset saves the cleaned filters and sort', async () => {
    answer(200, { id: 7 });
    const reload = mockReload();
    renderOrders({ saved: [{ ...paid, stale: true, sort: 'number' }, cancelled] });

    expect(within(rail()).getByText('View updated')).toBeTruthy();

    fireEvent.click(within(rail()).getByRole('button', { name: 'Reset' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/7', method: 'PUT', body: { filters: { status: 'paid' }, sort: 'number' } }]);
  });
});
