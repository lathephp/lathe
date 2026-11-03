import { cleanup as cleanupLiveRegion } from '@atlaskit/pragmatic-drag-and-drop-live-region';
import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
// The Board loads the save on its first move; loading it here keeps a cold transform out of the first test's wait.
import '../index/inlineSave';
import { clearUndo } from '../index/undo';
import { order, orders } from './board-support';
import { urlOf } from './preview-support';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

// An Undo pressed straight after a Board move, while the move's recount reloads the page's
// counts and the undo's own PATCH has not answered yet.

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  __panel.toasts.set([]);
  window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders' }));
});

afterEach(() => {
  clearUndo();
  cleanupLiveRegion();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const moving = 79194426;

interface Server {
  /** The PATCH bodies, in the order the page sent them. */
  patches: Record<string, unknown>[];
  /** Answers the oldest unanswered PATCH: the record takes its value and the token given. */
  answer: (updatedAt: string) => Promise<void>;
  /** The moving record's status as the server holds it. */
  status: () => string;
}

/** A server that holds the moving order's status: the columns, the counts and each PATCH read and write it. */
function server(): Server {
  let status = 'pending';
  const patches: Record<string, unknown>[] = [];
  const waiting: { value: string; resolve: (response: Response) => void }[] = [];

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = new URL(urlOf(input), 'http://localhost');

    if (init?.method === 'PATCH') {
      const body = JSON.parse(init.body as string) as Record<string, unknown>;
      patches.push(body);

      return new Promise<Response>((resolve) => waiting.push({ value: String(body.value), resolve }));
    }

    if (url.pathname.endsWith('/board/counts')) {
      const counts = { pending: status === 'pending' ? 2424 : 2423, paid: status === 'paid' ? 2553 : 2552, shipped: 2564, cancelled: 2460 };

      return Promise.resolve(
        new Response(JSON.stringify({ columns: Object.entries(counts).map(([value, count]) => ({ value, count: { value: count, exact: true, capped: false, estimated: false } })) }), { status: 200 }),
      );
    }

    if (url.pathname.endsWith('/board')) {
      const column = url.searchParams.get('column') ?? '';
      const ids = { pending: [79194427, 79194425], paid: [79194430, 79194420], shipped: [79194410], cancelled: [79194400] }[column] ?? [];
      const rows = (column === status ? [...ids, moving].sort((a, b) => b - a) : ids).map((id) => order(id, column));

      return Promise.resolve(new Response(JSON.stringify({ rows, hasMore: false }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  return {
    patches,
    status: () => status,
    answer: async (updatedAt) => {
      const next = waiting.shift();

      if (next === undefined) {
        throw new Error('No PATCH is waiting.');
      }

      await act(async () => {
        status = next.value;
        next.resolve(new Response(JSON.stringify({ fields: { status: { value: next.value, display: next.value.charAt(0).toUpperCase() + next.value.slice(1) } }, updatedAt }), { status: 200 }));
        await new Promise((resolve) => setTimeout(resolve, 0));
      });
    },
  };
}

const exact = (value: number) => ({ count: { value, exact: true, capped: false, estimated: false } });

function headers(): string[] {
  return Array.from(document.querySelectorAll<HTMLElement>('[data-lathe-board] section > header'), (header) => header.textContent);
}

function ids(name: string): string[] {
  return Array.from(screen.getByRole('region', { name }).querySelectorAll<HTMLElement>('[data-board-card]'), (card) => card.dataset.boardCard ?? '');
}

function card(id: number): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-board-card="${String(id)}"]`);

  if (found === null) {
    throw new Error(`No card ${String(id)}.`);
  }

  return found;
}

const toasts = () => __panel.toasts.get().map((toast) => toast.message);

const before = ['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled2,460'];

async function openBoard() {
  renderPage('Resources/Index', orders({ layout: 'board' }, { tabCounts: { pending: exact(120), paid: exact(80) } }), { url: '/lathe/resources/orders' });

  await waitFor(() => {
    expect(headers()).toEqual(before);
  });
}

async function pass(ms: number) {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, ms));
  });
}

/** Lets the move's recount fire, and answers it as the server would while the undo's PATCH waits. */
async function recount() {
  await pass(250);
  expect(requests).toHaveLength(1);
  expect(requests[0]?.headers['X-Inertia-Partial-Data']).toBe('tabCounts,navigationBadges');

  await act(async () => {
    requests[0]?.respond(200, { component: 'Resources/Index', url: '/lathe/resources/orders', props: { tabCounts: { pending: exact(119), paid: exact(81) } } });
    await new Promise((resolve) => setTimeout(resolve, 30));
  });
}

async function expectUndone(api: Server) {
  await waitFor(() => {
    expect(toasts()).toContain('Change undone');
  });
  await pass(30);

  expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
  expect(ids('Paid')).toEqual(['79194430', '79194420']);
  expect(headers()).toEqual(before);
  expect(api.patches).toEqual([
    { value: 'paid', _updated_at: '2026-10-14T09:00:00.000Z' },
    { value: 'pending', _updated_at: 't1' },
  ]);
  expect(api.status()).toBe('pending');
}

describe('an Undo pressed straight after a move', () => {
  it("from the toast, after Shift+Right, puts the card back while the recount reloads", async () => {
    const api = server();
    await openBoard();

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(1);
    });
    await api.answer('t1');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });

    act(() => {
      __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });

    await recount();
    await api.answer('t2');

    await expectUndone(api);
  });

  it('on Ctrl+Z, after a drag, puts the card back while the recount reloads', async () => {
    const api = server();
    await openBoard();

    fireEvent.dragStart(card(moving), { dataTransfer: transfer() });
    await pass(30);
    fireEvent.dragEnter(screen.getByRole('region', { name: 'Paid' }), { dataTransfer: transfer() });
    fireEvent.dragOver(screen.getByRole('region', { name: 'Paid' }), { dataTransfer: transfer() });
    fireEvent.drop(screen.getByRole('region', { name: 'Paid' }), { dataTransfer: transfer() });
    await waitFor(() => {
      expect(api.patches).toHaveLength(1);
    });
    await api.answer('t1');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });

    await recount();
    await api.answer('t2');

    await expectUndone(api);
  });

  it('on Ctrl+Z once the toast has gone', async () => {
    const api = server();
    await openBoard();

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(1);
    });
    await api.answer('t1');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    act(() => {
      __panel.toasts.set([]);
    });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });

    await recount();
    await api.answer('t2');

    await expectUndone(api);
  });
});

// jsdom has no DataTransfer; Pragmatic reads and writes the drag's types through it.
function transfer() {
  const data = new Map<string, string>();

  return {
    dropEffect: 'none',
    effectAllowed: 'all',
    get types() {
      return [...data.keys()];
    },
    items: { add: (value: string, type: string) => data.set(type, value) },
    setData: (type: string, value: string) => data.set(type, value),
    getData: (type: string) => data.get(type) ?? '',
    setDragImage: () => undefined,
  };
}
