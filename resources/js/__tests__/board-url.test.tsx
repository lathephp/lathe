import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { boardServer, order, orders } from './board-support';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

// Inertia's router and jsdom's history outlive a test, so the layout's URL tests have a file of their own.
let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

function layoutButton(name: string): HTMLElement {
  return within(screen.getByRole('group', { name: 'Layout' })).getByRole('button', { name });
}

function board(): HTMLElement | null {
  return document.querySelector<HTMLElement>('[data-lathe-board]');
}

describe('the layout in the URL', () => {
  it('writes ?layout=board in place and saves the choice, then Table takes it out again and reloads the rows', async () => {
    const server = boardServer();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const entries = window.history.length;

    expect(screen.getByRole('grid')).toBeTruthy();
    fireEvent.click(layoutButton('Board'));

    await waitFor(() => {
      expect(board()).not.toBeNull();
    });
    expect(window.location.search).toBe('?layout=board');
    expect(window.history.length).toBe(entries);
    expect(layoutButton('Board').getAttribute('aria-pressed')).toBe('true');
    await waitFor(() => {
      expect(server.puts).toEqual([{ url: '/lathe/api/resources/orders/layout', body: { layout: 'board' } }]);
    });

    fireEvent.click(layoutButton('Table'));

    await waitFor(() => {
      expect(screen.getByRole('grid')).toBeTruthy();
    });
    expect(board()).toBeNull();
    expect(window.location.search).toBe('');
    expect(window.history.length).toBe(entries);
    await waitFor(() => {
      expect(server.puts.map((put) => put.body)).toEqual([{ layout: 'board' }, { layout: 'table' }]);
    });
    // A move on the Board changed records the table drew before; it asks for its rows again.
    await waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    expect(requests[0]?.headers['X-Inertia-Partial-Data']).toBe('table');
    act(() => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: orders({ rows: [order(1, 'paid')] }), url: '/lathe/resources/orders' });
    });
    await waitFor(() => {
      expect(screen.queryByText('ORD-2')).toBeNull();
    });
    expect(window.location.search).toBe('');
  });

  it("opens the saved Board when the URL names no layout, and the table when it says table", async () => {
    boardServer();
    const saved = renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(board()).not.toBeNull();
    });
    expect(screen.queryByRole('grid')).toBeNull();
    expect(window.location.search).toBe('');
    saved.unmount();

    window.history.replaceState({}, '', '/lathe/resources/orders?layout=table');
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders?layout=table' });

    expect(screen.getByRole('grid')).toBeTruthy();
    expect(board()).toBeNull();
    expect(window.location.search).toBe('?layout=table');
  });

  it('keeps layout=board in the URL a filter visit from the Board writes', async () => {
    boardServer();
    const url = '/lathe/resources/orders?layout=board&filter[status]=paid&filter[old]=x';
    window.history.replaceState({}, '', url);
    renderPage('Resources/Index', orders({}, { staleFilters: ['old'] }), { url });

    await waitFor(() => {
      expect(board()).not.toBeNull();
    });
    fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Reset' }));

    await waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    const asked = new URL(requests[0]?.url ?? '', 'http://localhost');
    expect(asked.searchParams.get('layout')).toBe('board');
    act(() => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: orders(), url: `${asked.pathname}${asked.search}` });
    });

    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('layout')).toBe('board');
    });
    expect(new URLSearchParams(window.location.search).get('filter[old]')).toBeNull();
    expect(board()).not.toBeNull();
  });
});
