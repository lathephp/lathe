import { router } from '@inertiajs/react';
import { act, fireEvent, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { order, orders } from './board-support';
import { urlOf } from './preview-support';
import { fakeInertiaServer, renderPage } from './support';

// The save's chunk arrives 200 ms after it is asked for, as on a first visit over the network.
// Nothing in this file loads it ahead of the Board.
vi.mock('../index/inlineSave', async (importOriginal) => {
  await new Promise((resolve) => setTimeout(resolve, 200));

  return importOriginal();
});

const moving = 79194426;

beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
});

afterEach(() => {
  vi.restoreAllMocks();
});

async function pass(ms: number) {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, ms));
  });
}

it('takes back the first move of a Board whose save had to load, on a Ctrl+Z pressed straight after it', async () => {
  const patches: unknown[] = [];
  const waiting: ((response: Response) => void)[] = [];

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = new URL(urlOf(input), 'http://localhost');

    if (init?.method === 'PATCH') {
      patches.push(JSON.parse(init.body as string));

      return new Promise<Response>((resolve) => waiting.push(resolve));
    }

    if (url.pathname.endsWith('/board')) {
      const rows = url.searchParams.get('column') === 'pending' ? [order(moving, 'pending')] : [];

      return Promise.resolve(new Response(JSON.stringify({ rows, hasMore: false }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });
  await waitFor(() => {
    expect(document.querySelector(`[data-board-card="${String(moving)}"]`)).not.toBeNull();
  });
  // The Board has been open a moment before the user moves a card.
  await pass(300);

  const card = document.querySelector<HTMLElement>(`[data-board-card="${String(moving)}"]`);
  card?.focus();
  fireEvent.keyDown(card ?? document.body, { key: 'ArrowRight', shiftKey: true });
  await pass(5);
  fireEvent.keyDown(document.activeElement ?? document.body, { key: 'z', ctrlKey: true });

  await waitFor(() => {
    expect(patches).toHaveLength(1);
  });
  await act(async () => {
    waiting.shift()?.(new Response(JSON.stringify({ fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 't1' }), { status: 200 }));
    await new Promise((resolve) => setTimeout(resolve, 30));
  });

  await waitFor(() => {
    expect(patches).toEqual([
      { value: 'paid', _updated_at: '2026-10-14T09:00:00.000Z' },
      { value: 'pending', _updated_at: 't1' },
    ]);
  });
});
