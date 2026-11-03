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
// The card a test moves before `moving`, when it makes two moves.
const first = 79194427;

interface Server {
  /** The PATCH bodies, in the order the page sent them. */
  patches: Record<string, unknown>[];
  /** The id of the record each PATCH went to, in the same order. */
  targets: number[];
  /** Answers the oldest unanswered PATCH: the record takes its value and the token given. */
  answer: (updatedAt: string) => Promise<void>;
  /** Refuses the oldest unanswered PATCH with this status and body; the record keeps its value. */
  refuse: (code: number, data: Record<string, unknown>) => Promise<void>;
  /** A record's status as the server holds it. */
  status: (id?: number) => string;
}

/** A server that holds two orders' statuses: the columns, the counts and each PATCH read and write them. */
function server(): Server {
  const statuses = new Map([
    [first, 'pending'],
    [moving, 'pending'],
  ]);
  const patches: Record<string, unknown>[] = [];
  const targets: number[] = [];
  const waiting: { id: number; value: string; resolve: (response: Response) => void }[] = [];
  const held = (value: string) => [...statuses.values()].filter((each) => each === value).length;

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = new URL(urlOf(input), 'http://localhost');

    if (init?.method === 'PATCH') {
      const body = JSON.parse(init.body as string) as Record<string, unknown>;
      const id = Number(/\/orders\/(\d+)\/field\//.exec(url.pathname)?.[1]);
      patches.push(body);
      targets.push(id);

      return new Promise<Response>((resolve) => waiting.push({ id, value: String(body.value), resolve }));
    }

    if (url.pathname.endsWith('/board/counts')) {
      const counts = { pending: 2422 + held('pending'), paid: 2552 + held('paid'), shipped: 2564, cancelled: 2460 };

      return Promise.resolve(
        new Response(JSON.stringify({ columns: Object.entries(counts).map(([value, count]) => ({ value, count: { value: count, exact: true, capped: false, estimated: false } })) }), { status: 200 }),
      );
    }

    if (url.pathname.endsWith('/board')) {
      const column = url.searchParams.get('column') ?? '';
      const ids = { pending: [79194425], paid: [79194430, 79194420], shipped: [79194410], cancelled: [79194400] }[column] ?? [];
      const here = [...statuses].filter(([, value]) => value === column).map(([id]) => id);
      const rows = [...ids, ...here].sort((a, b) => b - a).map((id) => order(id, column));

      return Promise.resolve(new Response(JSON.stringify({ rows, hasMore: false }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  async function reply(change: (next: { id: number; value: string }) => Response) {
    const next = waiting.shift();

    if (next === undefined) {
      throw new Error('No PATCH is waiting.');
    }

    await act(async () => {
      next.resolve(change(next));
      await new Promise((resolve) => setTimeout(resolve, 0));
    });
  }

  return {
    patches,
    targets,
    status: (id = moving) => statuses.get(id) ?? '',
    answer: (updatedAt) =>
      reply(({ id, value }) => {
        statuses.set(id, value);

        return new Response(JSON.stringify({ fields: { status: { value, display: value.charAt(0).toUpperCase() + value.slice(1) } }, updatedAt }), { status: 200 });
      }),
    refuse: (code, data) => reply(() => new Response(JSON.stringify(data), { status: code })),
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

/** Moves the first card to Paid and lets its save answer, so its undo sits on the stack. */
async function moveFirst(api: Server) {
  card(first).focus();
  fireEvent.keyDown(card(first), { key: 'ArrowRight', shiftKey: true });
  await waitFor(() => {
    expect(api.patches).toHaveLength(1);
  });
  await api.answer('tA');
  await waitFor(() => {
    expect(toasts()).toContain('Status changed to Paid.');
  });
}

async function dropOnPaid(id: number) {
  const paid = screen.getByRole('region', { name: 'Paid' });
  fireEvent.dragStart(card(id), { dataTransfer: transfer() });
  await pass(30);
  fireEvent.dragEnter(paid, { dataTransfer: transfer() });
  fireEvent.dragOver(paid, { dataTransfer: transfer() });
  fireEvent.drop(paid, { dataTransfer: transfer() });
}

/** Presses Ctrl+Z while the second move's PATCH waits, then answers it and the undo's PATCH. */
async function undoWhileSaving(api: Server) {
  await waitFor(() => {
    expect(api.patches).toHaveLength(2);
  });
  fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
  await pass(30);
  // The undo waits for the save it takes back.
  expect(api.patches).toHaveLength(2);

  await api.answer('tB');
  await waitFor(() => {
    expect(api.patches).toHaveLength(3);
  });
  await api.answer('tU');
  await waitFor(() => {
    expect(toasts()).toContain('Change undone');
  });
  await pass(30);

  expect(ids('Pending')).toEqual(['79194426', '79194425']);
  expect(ids('Paid')).toEqual(['79194430', '79194427', '79194420']);
  expect(headers()).toEqual(['Pending2,423', 'Paid2,553', 'Shipped2,564', 'Cancelled2,460']);
  expect(api.patches).toEqual([
    { value: 'paid', _updated_at: '2026-10-14T09:00:00.000Z' },
    { value: 'paid', _updated_at: '2026-10-14T09:00:00.000Z' },
    { value: 'pending', _updated_at: 'tB' },
  ]);
  expect(api.targets).toEqual([first, moving, moving]);
  expect(api.status(first)).toBe('paid');
  expect(api.status(moving)).toBe('pending');
  // The taken-back move offers no Undo of its own once it saves: only the first move's toast says it changed.
  expect(toasts().filter((message) => message === 'Status changed to Paid.')).toHaveLength(1);
}

describe('a Ctrl+Z pressed before the last move has saved', () => {
  it('undoes that move after Shift+Right, not the one before it', async () => {
    const api = server();
    await openBoard();
    await moveFirst(api);

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });

    await undoWhileSaving(api);
  });

  it('undoes that move after a drag, not the one before it', async () => {
    const api = server();
    await openBoard();
    await moveFirst(api);

    await dropOnPaid(moving);

    await undoWhileSaving(api);
  });

  it('sends no undo for a move the server then refuses', async () => {
    const api = server();
    await openBoard();
    await moveFirst(api);

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });
    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
    await pass(30);
    await api.refuse(422, { message: 'The status is locked.', errors: { value: ['The status is locked.'] } });
    await waitFor(() => {
      expect(toasts()).toContain('The status is locked.');
    });
    await pass(30);

    expect(api.patches).toHaveLength(2);
    expect(toasts()).not.toContain('Change undone');
    expect(ids('Pending')).toEqual(['79194426', '79194425']);
    expect(ids('Paid')).toEqual(['79194430', '79194427', '79194420']);
    expect(headers()).toEqual(['Pending2,423', 'Paid2,553', 'Shipped2,564', 'Cancelled2,460']);
  });

  it('undoes the move before a refused one', async () => {
    const api = server();
    await openBoard();
    await moveFirst(api);

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });
    await api.refuse(422, { message: 'The status is locked.', errors: { value: ['The status is locked.'] } });
    await waitFor(() => {
      expect(toasts()).toContain('The status is locked.');
    });

    fireEvent.keyDown(document.body, { key: 'z', ctrlKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(3);
    });
    await api.answer('tU');
    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    await pass(30);

    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
    expect(ids('Paid')).toEqual(['79194430', '79194420']);
    expect(headers()).toEqual(before);
    expect(api.patches[2]).toEqual({ value: 'pending', _updated_at: 'tA' });
    expect(api.targets).toEqual([first, moving, first]);
    expect(api.status(first)).toBe('pending');
    expect(api.status(moving)).toBe('pending');
  });
});

describe('a Ctrl+Z pressed after the last move has saved', () => {
  it("takes that move's toast away, so the Undo left on screen is the first move's", async () => {
    const api = server();
    await openBoard();
    await moveFirst(api);

    card(moving).focus();
    fireEvent.keyDown(card(moving), { key: 'ArrowRight', shiftKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(2);
    });
    await api.answer('tB');
    await waitFor(() => {
      expect(toasts().filter((message) => message === 'Status changed to Paid.')).toHaveLength(2);
    });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });
    await waitFor(() => {
      expect(api.patches).toHaveLength(3);
    });
    await api.answer('tU');
    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });

    expect(toasts().filter((message) => message === 'Status changed to Paid.')).toHaveLength(1);
    expect(api.targets).toEqual([first, moving, moving]);

    // The Undo still on screen takes back the first move, the one its toast told of.
    __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    await waitFor(() => {
      expect(api.patches).toHaveLength(4);
    });

    expect(api.targets).toEqual([first, moving, moving, first]);
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
