import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { boardServer, orders } from './board-support';
import { panel, urlOf } from './preview-support';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

/** The Board's server, answering each preview request with the card's own number. */
function server(): string[] {
  boardServer();
  const board = vi.mocked(globalThis.fetch).getMockImplementation();
  const previews: string[] = [];

  vi.mocked(globalThis.fetch).mockImplementation((input, init) => {
    const match = /\/resources\/orders\/([^/?]+)\/preview$/.exec(urlOf(input));

    if (match === null) {
      return (board as typeof fetch)(input, init);
    }

    previews.push(match[1] as string);
    const body = { id: match[1], title: `ORD-${String(match[1])}`, subtitle: '', avatar: null, fields: [], abilities: { view: true, update: true }, actions: { actions: [], pivotActions: [], counts: {} } };

    return Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));
  });

  return previews;
}

function card(id: string): HTMLElement {
  return document.querySelector<HTMLElement>(`[data-board-card="${id}"]`) as HTMLElement;
}

async function board() {
  renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

  await waitFor(() => {
    expect(card('cancelled-1-25')).not.toBeNull();
  });
}

function press(key: string) {
  fireEvent.keyDown(document.activeElement ?? document.body, { key });
}

describe('the Board from the keyboard', () => {
  it('moves the focus down a column and across to the nearest card of the next column', async () => {
    server();
    await board();
    card('pending-1-1').focus();

    press('ArrowDown');
    press('ArrowDown');
    expect(document.activeElement).toBe(card('pending-1-3'));

    press('ArrowRight');
    expect(document.activeElement).toBe(card('paid-1-3'));

    press('ArrowUp');
    press('ArrowLeft');
    expect(document.activeElement).toBe(card('pending-1-2'));

    press('ArrowLeft');
    press('ArrowUp');
    press('ArrowUp');
    expect(document.activeElement).toBe(card('pending-1-1'));
  });

  it('toggles the preview panel on the focused card with Space, and an open panel follows the focus', async () => {
    const previews = server();
    await board();
    card('paid-1-2').focus();

    press(' ');
    await waitFor(() => {
      expect(panel().textContent).toContain('ORD-paid-1-2');
    });

    press('ArrowRight');
    await waitFor(() => {
      expect(panel().textContent).toContain('ORD-shipped-1-2');
    });

    press(' ');
    await waitFor(() => {
      expect(panel()).toBeNull();
    });
    expect(previews).toEqual(['paid-1-2', 'shipped-1-2']);
  });

  it('opens the preview panel on a clicked card', async () => {
    server();
    await board();

    fireEvent.click(card('shipped-1-4'));

    await waitFor(() => {
      expect(panel().textContent).toContain('ORD-shipped-1-4');
    });
  });

  it('has no selection checkbox on a card', async () => {
    server();
    await board();

    expect(document.querySelectorAll('[data-lathe-board] [role=checkbox], [data-lathe-board] input[type=checkbox]')).toHaveLength(0);
    expect(screen.queryByRole('checkbox')).toBeNull();
  });
});
