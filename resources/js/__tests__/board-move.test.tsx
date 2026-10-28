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
import { renderPage } from './support';

// Moving Orders' cards between status columns: by Shift+Left/Right, the Move to menu and a drag.

beforeEach(() => {
  __panel.toasts.set([]);
});

afterEach(() => {
  // The stack and the live region are the tab's, so no test leaves either for the next.
  clearUndo();
  cleanupLiveRegion();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

const loaded: Record<string, number[]> = {
  pending: [79194427, 79194426, 79194425],
  paid: [79194430, 79194420],
  shipped: [79194410],
  cancelled: [79194400],
};

interface Patch {
  path: string;
  body: Record<string, unknown>;
}

const ok = (value: string, updatedAt: string) =>
  new Response(JSON.stringify({ fields: { status: { value, display: value.charAt(0).toUpperCase() + value.slice(1) } }, updatedAt }), { status: 200 });

/** Answers the Board's pages and counts, and each PATCH with the next reply given. */
function moveServer(replies: (Response | Promise<Response>)[], abilities: Record<string, boolean> = { view: true, update: true, inlineEdit: true }, more: Record<string, number[]> = {}) {
  const patches: Patch[] = [];

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = new URL(urlOf(input), 'http://localhost');

    if (init?.method === 'PATCH') {
      patches.push({ path: url.pathname, body: JSON.parse(init.body as string) as Record<string, unknown> });

      return Promise.resolve(replies.shift() ?? new Response(null, { status: 500 }));
    }

    if (url.pathname.endsWith('/board/counts')) {
      const counts = { pending: 2424, paid: 2552, shipped: 2564, cancelled: 2460 };

      return Promise.resolve(
        new Response(JSON.stringify({ columns: Object.entries(counts).map(([value, count]) => ({ value, count: { value: count, exact: true, capped: false, estimated: false } })) }), { status: 200 }),
      );
    }

    if (url.pathname.endsWith('/board')) {
      const column = url.searchParams.get('column') ?? '';
      const first = (url.searchParams.get('offset') ?? '0') === '0';
      const page = !first ? more[column] : url.searchParams.has('q') ? [] : loaded[column];
      const rows = (page ?? []).map((id) => ({ ...order(id, column), abilities }));

      return Promise.resolve(new Response(JSON.stringify({ rows, hasMore: first && column in more }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  return patches;
}

function column(name: string): HTMLElement {
  return screen.getByRole('region', { name });
}

function ids(name: string): string[] {
  return Array.from(column(name).querySelectorAll<HTMLElement>('[data-board-card]'), (card) => card.dataset.boardCard ?? '');
}

function card(id: number): HTMLElement {
  const found = document.querySelector<HTMLElement>(`[data-board-card="${String(id)}"]`);

  if (found === null) {
    throw new Error(`No card ${String(id)}.`);
  }

  return found;
}

function headers(): string[] {
  return Array.from(document.querySelectorAll<HTMLElement>('[data-lathe-board] section > header'), (header) => header.textContent);
}

const toasts = () => __panel.toasts.get().map((toast) => toast.message);

async function openBoard() {
  renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

  await waitFor(() => {
    expect(headers()).toEqual(['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled2,460']);
  });
}

function press(id: number, key: 'ArrowLeft' | 'ArrowRight') {
  card(id).focus();
  fireEvent.keyDown(document.activeElement ?? document.body, { key, shiftKey: true });
}

async function settle() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 30));
  });
}

const path = '/lathe/api/resources/orders/79194426/field/status';
const token = '2026-10-14T09:00:00.000Z';

describe('moving a card by keyboard', () => {
  it('moves a focused Pending card to Paid on Shift+Right with one PATCH, and both counts change', async () => {
    const patches = moveServer([ok('paid', 't1')]);
    await openBoard();

    press(79194426, 'ArrowRight');

    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    expect(patches).toEqual([{ path, body: { value: 'paid', _updated_at: token } }]);
    expect(ids('Pending')).toEqual(['79194427', '79194425']);
    expect(ids('Paid')).toEqual(['79194430', '79194426', '79194420']);
    expect(headers()).toEqual(['Pending2,423', 'Paid2,553', 'Shipped2,564', 'Cancelled2,460']);
    expect(__panel.toasts.get().find((toast) => toast.message === 'Status changed to Paid.')?.action?.label).toBe('Undo');
    expect(document.activeElement).toBe(card(79194426));
  });

  it('sends nothing on Shift+Left in the first column or Shift+Right in the last', async () => {
    const patches = moveServer([]);
    await openBoard();

    press(79194426, 'ArrowLeft');
    press(79194400, 'ArrowRight');
    await settle();

    expect(patches).toEqual([]);
    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
    expect(ids('Cancelled')).toEqual(['79194400']);
  });

  it('announces the move in the live region', async () => {
    moveServer([ok('paid', 't1')]);
    await openBoard();

    press(79194426, 'ArrowRight');

    expect(await screen.findByText('ORD-79194426 moved to Paid', {}, { timeout: 2000 })).toBeTruthy();
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

describe('dragging a card', () => {
  it('moves a Pending card dropped on the Paid column as Shift+Right does', async () => {
    const patches = moveServer([ok('paid', 't1')]);
    await openBoard();

    fireEvent.dragStart(card(79194426), { dataTransfer: transfer() });
    await settle();
    fireEvent.dragEnter(column('Paid'), { dataTransfer: transfer() });
    fireEvent.dragOver(column('Paid'), { dataTransfer: transfer() });
    fireEvent.drop(column('Paid'), { dataTransfer: transfer() });

    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    expect(patches).toEqual([{ path, body: { value: 'paid', _updated_at: token } }]);
    expect(ids('Paid')).toEqual(['79194430', '79194426', '79194420']);
    expect(headers()).toEqual(['Pending2,423', 'Paid2,553', 'Shipped2,564', 'Cancelled2,460']);
  });
});

describe('the Move to menu', () => {
  it('lists every other column, and Shipped moves the card there with one PATCH', async () => {
    const patches = moveServer([ok('shipped', 't1')]);
    await openBoard();

    fireEvent.click(within(card(79194426)).getByRole('button', { name: 'Move ORD-79194426' }));
    const menu = screen.getByRole('menu', { name: 'Move to' });

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['Paid', 'Shipped', 'Cancelled']);

    fireEvent.click(within(menu).getByRole('menuitem', { name: 'Shipped' }));

    await waitFor(() => {
      expect(ids('Shipped')).toEqual(['79194426', '79194410']);
    });
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Shipped.');
    });
    expect(patches).toEqual([{ path, body: { value: 'shipped', _updated_at: token } }]);
    expect(headers()).toEqual(['Pending2,423', 'Paid2,552', 'Shipped2,565', 'Cancelled2,460']);
  });
});

describe('undoing a move', () => {
  it('moves the card back on Ctrl+Z with one PATCH against the token the move returned', async () => {
    const patches = moveServer([ok('paid', 't1'), ok('pending', 't2')]);
    await openBoard();
    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    expect(patches.map((patch) => patch.body)).toEqual([
      { value: 'paid', _updated_at: token },
      { value: 'pending', _updated_at: 't1' },
    ]);
    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
    expect(ids('Paid')).toEqual(['79194430', '79194420']);
    expect(headers()).toEqual(['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled2,460']);
  });

  it('still sends the undo when a search has since taken the card off the Board', async () => {
    const patches = moveServer([ok('paid', 't1'), ok('pending', 't2')]);
    await openBoard();
    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    act(() => {
      router.replace({ url: '/lathe/resources/orders?q=zzz', preserveState: true, preserveScroll: true });
    });
    await waitFor(() => {
      expect(document.querySelectorAll('[data-board-card]')).toHaveLength(0);
    });

    fireEvent.keyDown(document.body, { key: 'z', ctrlKey: true });

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    expect(patches[1]?.body).toEqual({ value: 'pending', _updated_at: 't1' });
  });

  it("does the same from the toast's Undo", async () => {
    const patches = moveServer([ok('paid', 't1'), ok('pending', 't2')]);
    await openBoard();
    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });

    act(() => {
      __panel.toasts.get().find((toast) => toast.action !== undefined)?.action?.onClick();
    });

    await waitFor(() => {
      expect(toasts()).toContain('Change undone');
    });
    expect(patches[1]?.body).toEqual({ value: 'pending', _updated_at: 't1' });
    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
    expect(headers()).toEqual(['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled2,460']);
  });
});

describe('two moves of one card', () => {
  it('sends the second after the first answers, with the token it returned', async () => {
    let finish: (response: Response) => void = () => undefined;
    const patches = moveServer([new Promise<Response>((resolve) => (finish = resolve)), ok('shipped', 't2')]);
    await openBoard();

    press(79194426, 'ArrowRight');
    press(79194426, 'ArrowRight');
    await settle();

    expect(patches).toHaveLength(1);
    expect(ids('Shipped')).toContain('79194426');

    finish(ok('paid', 't1'));

    await waitFor(() => {
      expect(patches).toHaveLength(2);
    });
    expect(patches[1]?.body).toEqual({ value: 'shipped', _updated_at: 't1' });
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Shipped.');
    });
    expect(ids('Shipped')).toEqual(['79194426', '79194410']);
  });
});

describe('Show more after a move', () => {
  it('asks from the cards the column still holds once one has left it', async () => {
    moveServer([ok('paid', 't1')], undefined, { pending: [79194424] });
    await openBoard();
    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });
    const asked = vi.mocked(globalThis.fetch).mock.calls.length;

    fireEvent.click(within(column('Pending')).getByRole('button', { name: 'Show more' }));

    await waitFor(() => {
      expect(ids('Pending')).toEqual(['79194427', '79194425', '79194424']);
    });
    const more = vi.mocked(globalThis.fetch).mock.calls.slice(asked).map(([input]) => new URL(urlOf(input), 'http://localhost').searchParams);
    expect(more.map((params) => [params.get('column'), params.get('offset')])).toEqual([['pending', '2']]);
  });

  it('draws a card the next page sends again only once', async () => {
    moveServer([ok('paid', 't1')], undefined, { paid: [79194426, 79194419] });
    await openBoard();
    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(toasts()).toContain('Status changed to Paid.');
    });

    fireEvent.click(within(column('Paid')).getByRole('button', { name: 'Show more' }));

    await waitFor(() => {
      expect(ids('Paid')).toEqual(['79194430', '79194426', '79194420', '79194419']);
    });
  });
});

describe('a refused move', () => {
  it.each([
    [422, { message: 'The status is locked.', errors: { value: ['The status is locked.'] } }],
    [403, { message: 'You may not change this order.' }],
  ])('puts the card back in Pending at its place on a %i, and the toast gives the reason', async (code, data) => {
    let answer: (response: Response) => void = () => undefined;
    moveServer([new Promise<Response>((resolve) => (answer = resolve))]);
    await openBoard();

    press(79194426, 'ArrowRight');
    await waitFor(() => {
      expect(ids('Paid')).toEqual(['79194430', '79194426', '79194420']);
    });
    expect(headers()).toEqual(['Pending2,423', 'Paid2,553', 'Shipped2,564', 'Cancelled2,460']);

    await act(async () => {
      answer(new Response(JSON.stringify(data), { status: code }));
      await new Promise((resolve) => setTimeout(resolve, 0));
    });

    await waitFor(() => {
      expect(toasts()).toContain(data.message);
    });
    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
    expect(ids('Paid')).toEqual(['79194430', '79194420']);
    expect(headers()).toEqual(['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled2,460']);
  });
});

describe('a user who may not edit', () => {
  it.each([
    ['inlineEdit', { view: true, update: true, inlineEdit: false }],
    ['update', { view: true, update: false, inlineEdit: true }],
  ])('without %s gets no Move to button, no drag and no Shift+Right move', async (_, abilities) => {
    const patches = moveServer([ok('paid', 't1')], abilities);
    await openBoard();

    expect(within(card(79194426)).queryByRole('button', { name: 'Move ORD-79194426' })).toBeNull();
    expect(card(79194426).draggable).toBe(false);

    press(79194426, 'ArrowRight');
    await settle();

    expect(patches).toEqual([]);
    expect(ids('Pending')).toEqual(['79194427', '79194426', '79194425']);
  });

  it('can drag a card it may edit', async () => {
    moveServer([]);
    await openBoard();

    expect(card(79194426).draggable).toBe(true);
  });
});
