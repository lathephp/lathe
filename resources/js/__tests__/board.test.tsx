import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { boardServer, orders, status } from './board-support';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function column(name: string): HTMLElement {
  return screen.getByRole('region', { name });
}

function cards(name: string): HTMLElement[] {
  return Array.from(column(name).querySelectorAll<HTMLElement>('[data-board-card]'));
}

function headers(): HTMLElement[] {
  return Array.from(document.querySelectorAll<HTMLElement>('[data-lathe-board] section > header'));
}

async function settle() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 30));
  });
}

describe('the Board', () => {
  it('draws a column per option in order, each headed by its meter and its capped count', async () => {
    boardServer({ counts: { pending: 2424, paid: 2552, shipped: 2564, cancelled: 'capped' } });
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(headers().map((header) => header.textContent)).toEqual(['Pending2,424', 'Paid2,552', 'Shipped2,564', 'Cancelled10,000+']);
    });
    expect(headers().map((header) => header.querySelector('[data-lathe-meter]')?.textContent)).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled']);
    expect(screen.queryByRole('grid')).toBeNull();
  });

  it('shows 25 cards per column, and Show more adds the next 25 to that column alone', async () => {
    const server = boardServer();
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(cards('Paid')).toHaveLength(25);
    });
    expect(cards('Pending')).toHaveLength(25);

    fireEvent.click(within(column('Pending')).getByRole('button', { name: 'Show more' }));

    await waitFor(() => {
      expect(cards('Pending')).toHaveLength(50);
    });
    expect(cards('Paid')).toHaveLength(25);
    expect(within(column('Pending')).queryByRole('button', { name: 'Show more' })).toBeNull();
    expect(within(column('Paid')).getByRole('button', { name: 'Show more' })).toBeTruthy();
    const second = server.asked.filter((url) => new URL(url, 'http://localhost').searchParams.get('page') === '2');
    expect(second.map((url) => new URL(url, 'http://localhost').searchParams.get('column'))).toEqual(['pending']);
  });

  it("draws a card from the phoneCard picks: title, the status meter, the total and the customer", async () => {
    boardServer();
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(cards('Paid')).toHaveLength(25);
    });
    const card = cards('Paid')[0] as HTMLElement;

    expect(card.dataset.boardCard).toBe('paid-1-1');
    expect(card.tabIndex).toBe(0);
    expect(card.querySelector('[data-lathe-meter]')?.textContent).toBe('Paid');
    expect(Array.from(card.children, (line) => line.textContent)).toEqual(['ORD-paid-1-1Paid', 'Gerard Reichert', '$164.98']);
  });

  it('reads "No orders" in an empty column', async () => {
    boardServer({ empty: ['shipped'] });
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(within(column('Shipped')).getByText('No orders')).toBeTruthy();
    });
    expect(within(column('Paid')).queryByText('No orders')).toBeNull();
  });

  it('ends a nullable field with a No value column, asked for with an empty column', async () => {
    const server = boardServer({ counts: { '': 12 } });
    renderPage('Resources/Index', orders({ layout: 'board', board: { field: { ...status, nullable: true }, noValue: true } }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(headers().map((header) => header.textContent)).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled', 'No value12']);
    });
    expect(cards('No value')).toHaveLength(25);
    const columns = server.asked.filter((url) => url.includes('/board?')).map((url) => new URL(url, 'http://localhost').searchParams.get('column'));
    expect(columns).toEqual(['pending', 'paid', 'shipped', 'cancelled', '']);
  });

  it("sends the list's filter and search with every column and count request", async () => {
    const server = boardServer();
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders?filter[customer]=12&q=ann&preview=3' });

    await waitFor(() => {
      expect(server.asked.some((url) => url.includes('/board/counts'))).toBe(true);
    });
    const board = server.asked.filter((url) => url.includes('/board'));
    expect(board).toHaveLength(5);
    for (const url of board) {
      const params = new URL(url, 'http://localhost').searchParams;
      expect([params.get('filter[customer]'), params.get('q'), params.get('preview'), params.get('layout')]).toEqual(['12', 'ann', null, null]);
    }
  });
});

describe('where the Board is not offered', () => {
  it('shows the switch on a Resource with a board, and none on a lens or without one', () => {
    boardServer();
    const { unmount } = renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    expect(within(screen.getByRole('group', { name: 'Layout' })).getAllByRole('button').map((button) => button.textContent)).toEqual(['Table', 'Board']);
    unmount();

    const lens = renderPage('Resources/Index', orders({}, { lens: { uriKey: 'pending-orders', name: 'Pending Orders' } }), { url: '/lathe/resources/orders/lens/pending-orders' });
    expect(screen.queryByRole('group', { name: 'Layout' })).toBeNull();
    lens.unmount();

    renderPage('Resources/Index', orders({ board: null }), { url: '/lathe/resources/orders' });
    expect(screen.queryByRole('group', { name: 'Layout' })).toBeNull();
  });

  it('keeps the card list on a phone given the board layout, and asks for no column', async () => {
    const server = boardServer();
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: query === '(width < 48rem)' }));
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders?layout=board' });
    await settle();

    expect(screen.queryByRole('group', { name: 'Layout' })).toBeNull();
    expect(document.querySelector('[data-lathe-board]')).toBeNull();
    expect(document.querySelectorAll('[data-lathe-cards] li')).toHaveLength(2);
    expect(server.asked.filter((url) => url.includes('/board'))).toEqual([]);
  });
});
