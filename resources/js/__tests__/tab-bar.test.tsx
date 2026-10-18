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
        { key: 'all', name: 'All', icon: null, badge: null, badgeType: null, deferredBadge: false },
        { key: 'pending', name: 'Pending', icon: null, badge: null, badgeType: 'warning', deferredBadge: options.deferred?.includes('pending') ?? false },
        { key: 'paid', name: 'Paid', icon: null, badge: null, badgeType: null, deferredBadge: options.deferred?.includes('paid') ?? false },
      ],
      active: 'pending',
      saved: options.saved ?? [paid, cancelled],
      activeSaved: null,
    },
  };

  if (options.tabCounts !== undefined) {
    props.tabCounts = options.tabCounts;
  }

  return renderPage('Resources/Index', props, { url: options.url ?? '/lathe/resources/orders?tab=pending' });
}

/** The tab buttons in the strip, by their text, in order. */
function tabTexts(): string[] {
  const strip = screen.getByRole('navigation', { name: 'Tabs' });

  return Array.from(strip.querySelectorAll('[data-tab]'), (tab) => tab.textContent);
}

function savedTab(name: string): HTMLElement {
  const strip = screen.getByRole('navigation', { name: 'Tabs' });

  return Array.from(strip.querySelectorAll<HTMLElement>('[data-tab^="saved:"]')).find((tab) => tab.textContent.startsWith(name)) as HTMLElement;
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

describe('tab strip', () => {
  it('shows developer tabs, then saved tabs, with badges once the counts arrive', () => {
    const { unmount } = renderOrders();

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Paid', 'Cancelled']);
    unmount();

    renderOrders({ tabCounts: { pending: { count: capped }, 'saved:7': { count: { value: 1234, exact: true, capped: false, estimated: false } } } });

    expect(tabTexts()).toEqual(['All', 'Pending10,000+', 'Paid', 'Paid1,234', 'Cancelled']);
  });

  it('holds a placeholder badge in each counting tab until the counts arrive', () => {
    const { unmount } = renderOrders({ deferred: ['pending', 'paid'] });
    const placeholders = (name: string) => Array.from(document.querySelectorAll(`[data-tab="${name}"] [data-badge-placeholder]`));

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

  it('clears tab when the first tab is chosen and keeps the filters', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ url: '/lathe/resources/orders?tab=pending&filter[status]=paid' });
    const strip = screen.getByRole('navigation', { name: 'Tabs' });

    fireEvent.click(within(strip).getByRole('button', { name: 'All' }));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ filter: { status: 'paid' } });
  });

  it('opens a saved tab as saved, without the filters, search and sort on screen', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ url: '/lathe/resources/orders?tab=pending&filter[status]=cancelled&sort=-total_cents&q=x&trashed=with&group=status' });

    fireEvent.click(savedTab('Paid'));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ saved_tab: '7' });
  });
});

describe('saving a view', () => {
  it('posts the current filters, search and sort with the name, count switch and badge colour', async () => {
    answer(201, { id: 9 });
    const reload = mockReload();
    renderOrders({ url: '/lathe/resources/orders?filter[status]=paid&q=ann&sort=-total_cents' });

    fireEvent.click(screen.getByRole('button', { name: 'Save view' }));
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
        body: { name: 'Big paid', filters: { status: 'paid' }, q: 'ann', sort: '-total_cents', trashed: null, show_count: true, badge_type: 'danger' },
      },
    ]);
    expect(reload.mock.calls[0]?.[0]).toMatchObject({ only: ['tabs', 'tabCounts'] });
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});

describe('saved tab menu', () => {
  it('renames a saved tab', async () => {
    answer(200, { id: 7 });
    const reload = mockReload();
    renderOrders();

    fireEvent.click(screen.getByRole('button', { name: 'Options for Paid' }));
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

    fireEvent.click(screen.getByRole('button', { name: 'Options for Cancelled' }));
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
  it('moves a focused tab right on Alt+Right, saves the order and announces it', async () => {
    answer(204);
    const reload = mockReload();
    renderOrders();

    fireEvent.keyDown(savedTab('Paid'), { key: 'ArrowRight', altKey: true });

    expect(tabTexts()).toEqual(['All', 'Pending', 'Paid', 'Cancelled', 'Paid']);
    expect(screen.getByText('Moved Paid to position 2').getAttribute('aria-live')).toBe('polite');
    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/reorder', method: 'PUT', body: { ids: [8, 7] } }]);
  });

  it('puts the old order back when the server answers 422', async () => {
    answer(422, { message: 'Send every one of your saved tabs once', errors: { ids: ['Send every one of your saved tabs once'] } });
    const reload = mockReload();
    renderOrders();

    fireEvent.keyDown(savedTab('Cancelled'), { key: 'ArrowLeft', altKey: true });

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

    expect(screen.getByText('View updated')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    await vi.waitFor(() => {
      expect(reload).toHaveBeenCalledTimes(1);
    });
    expect(calls).toEqual([{ url: '/lathe/api/resources/orders/saved-tabs/7', method: 'PUT', body: { filters: { status: 'paid' }, sort: 'number' } }]);
  });
});
