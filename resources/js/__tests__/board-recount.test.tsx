import { cleanup as cleanupLiveRegion } from '@atlaskit/pragmatic-drag-and-drop-live-region';
import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
// The Board loads the save on its first move; loading it here keeps a cold transform out of the first test's wait.
import '../index/inlineSave';
import { clearUndo } from '../index/undo';
import { order, orders } from './board-support';
import { urlOf } from './preview-support';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

// What a Board move changes outside the Board: the open preview's header, the views rail's
// counts and the sidebar's badge, through a real partial reload.

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  __panel.toasts.set([]);
  // The panel's cache outlives a test; a save to orders empties it.
  window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders' }));
});

afterEach(() => {
  clearUndo();
  cleanupLiveRegion();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const ok = (value: string, updatedAt: string) =>
  new Response(JSON.stringify({ fields: { status: { value, display: value.charAt(0).toUpperCase() + value.slice(1) } }, updatedAt }), { status: 200 });

// The preview always answers with the record as it was before the move.
const pendingPreview = {
  id: 79194426,
  title: 'ORD-79194426',
  fields: [{ key: 'status', label: 'Status', component: 'select-field', value: 'pending', display: 'Pending', options: [{ value: 'pending', label: 'Pending' }], badges: { pending: 'warning' } }],
  abilities: { update: true },
  actions: { actions: [] },
};

/** Answers the Board's pages and counts, the preview, and each PATCH with the next reply given. */
function moveServer(replies: Response[]) {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = new URL(urlOf(input), 'http://localhost');

    if (init?.method === 'PATCH') {
      return Promise.resolve(replies.shift() ?? new Response(null, { status: 500 }));
    }

    if (url.pathname.endsWith('/preview')) {
      return Promise.resolve(new Response(JSON.stringify(pendingPreview), { status: 200 }));
    }

    if (url.pathname.endsWith('/board')) {
      const column = url.searchParams.get('column') ?? '';
      const ids = column === 'pending' ? [79194427, 79194426] : column === 'paid' ? [79194430] : [];

      return Promise.resolve(new Response(JSON.stringify({ rows: ids.map((id) => order(id, column)), hasMore: false }), { status: 200 }));
    }

    if (url.pathname.endsWith('/board/counts')) {
      return Promise.resolve(new Response(JSON.stringify({ columns: [] }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });
}

const exact = (value: number) => ({ count: { value, exact: true, capped: false, estimated: false } });

const tabs = {
  items: [
    { key: 'pending', name: 'Pending', icon: null, badge: null, badgeType: 'warning', deferredBadge: true },
    { key: 'paid', name: 'Paid', icon: null, badge: null, badgeType: null, deferredBadge: true },
  ],
  active: null,
  saved: [],
  activeSaved: null,
};

function openBoard() {
  renderPage(
    'Resources/Index',
    orders({ layout: 'board' }, { tabs, tabCounts: { pending: exact(120), paid: exact(80) }, navigationBadges: { '/lathe/resources/orders': { value: '120', type: 'warning' } } }),
    { url: '/lathe/resources/orders' },
  );
}

function card(id: number): HTMLElement {
  return document.querySelector<HTMLElement>(`[data-board-card="${String(id)}"]`) as HTMLElement;
}

function moveRight(id: number) {
  card(id).focus();
  fireEvent.keyDown(card(id), { key: 'ArrowRight', shiftKey: true });
}

const toasts = () => __panel.toasts.get().map((toast) => toast.message);

function railTabs(): string[] {
  const rail = screen.getAllByRole('navigation', { name: 'Views' })[0] as HTMLElement;

  return Array.from(rail.querySelectorAll('[data-tab]'), (tab) => tab.textContent);
}

function sidebarOrders(): string {
  return within(screen.getByRole('complementary')).getByRole('link', { name: /^Orders/ }).textContent;
}

async function answer(request: FakeRequest | undefined, pending: number, paid: number) {
  await act(async () => {
    request?.respond(200, {
      component: 'Resources/Index',
      url: '/lathe/resources/orders',
      props: { tabCounts: { pending: exact(pending), paid: exact(paid) }, navigationBadges: { '/lathe/resources/orders': { value: String(pending), type: 'warning' } } },
    });
    await Promise.resolve();
  });
}

describe('a Board move with the preview open on its card', () => {
  it('makes the panel header read the new status, and the old one again after Undo', async () => {
    moveServer([ok('paid', 't1'), ok('pending', 't2')]);
    openBoard();
    await waitFor(() => {
      expect(card(79194426)).not.toBeNull();
    });

    fireEvent.click(card(79194426));
    const header = () => document.querySelector('[data-lathe-preview] header')?.textContent ?? '';
    await waitFor(() => {
      expect(header()).toBe('ORD-79194426PendingOpen full pageClose');
    });

    moveRight(79194426);

    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    await waitFor(() => {
      expect(header()).toBe('ORD-79194426PaidOpen full pageClose');
    });

    act(() => {
      __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    });

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    expect(header()).toBe('ORD-79194426PendingOpen full pageClose');
  });
});

describe('the counts outside the Board', () => {
  it('reloads the tab counts and the sidebar badges once after a move and once after its undo', async () => {
    moveServer([ok('paid', 't1'), ok('pending', 't2')]);
    openBoard();
    await waitFor(() => {
      expect(card(79194426)).not.toBeNull();
    });
    expect(railTabs()).toEqual(['Pending120', 'Paid80']);
    expect(sidebarOrders()).toBe('Orders120');

    moveRight(79194426);

    await waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    expect(requests[0]?.headers['X-Inertia-Partial-Data']).toBe('tabCounts,navigationBadges');
    await answer(requests[0], 119, 81);

    expect(railTabs()).toEqual(['Pending119', 'Paid81']);
    expect(sidebarOrders()).toBe('Orders119');

    act(() => {
      __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    });

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    await waitFor(() => {
      expect(requests).toHaveLength(2);
    });
    expect(requests[1]?.headers['X-Inertia-Partial-Data']).toBe('tabCounts,navigationBadges');
    await answer(requests[1], 118, 82);

    expect(railTabs()).toEqual(['Pending118', 'Paid82']);
    expect(sidebarOrders()).toBe('Orders118');
  });
});
