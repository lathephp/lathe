import { router } from '@inertiajs/react';
import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

function orders(clickAction: string) {
  return {
    resource: {
      uriKey: 'orders',
      label: 'Orders',
      singularLabel: 'Order',
      perPageOptions: [25],
      abilities: { create: false },
      definition: {
        debounce: 0.5,
        paginationType: 'links',
        clickAction,
        polling: null,
        showPollingToggle: false,
        tableStyle: 'default',
        showColumnBorders: false,
        softDeletes: false,
      },
    },
    table: {
      columns: [{ key: 'number', label: 'Number', component: 'text' }],
      rows: [1, 2].map((id) => ({ id, title: `ORD-${String(id)}`, cells: { number: { value: `ORD-${String(id)}`, display: `ORD-${String(id)}` } }, abilities: { view: true, update: true } })),
      rowKey: 'id',
    },
    state: { page: 1, perPage: 25, hasMorePages: false },
  };
}

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe("a row's title cell", () => {
  it('prefetches the record after a 75 ms hover, and not after 50 ms', async () => {
    renderPage('Resources/Index', orders('detail'), { url: '/lathe/resources/orders' });
    const title = screen.getByText('ORD-2');

    fireEvent.mouseEnter(title);
    await act(() => vi.advanceTimersByTimeAsync(50));
    fireEvent.mouseLeave(title);
    await act(() => vi.advanceTimersByTimeAsync(200));
    expect(requests).toHaveLength(0);

    fireEvent.mouseEnter(title);
    await act(() => vi.advanceTimersByTimeAsync(75));
    expect(requests).toHaveLength(1);
    expect(requests[0]?.url).toContain('/lathe/resources/orders/2');
    expect(requests[0]?.headers.Purpose).toBe('prefetch');
  });

  it('opens the record page at once with its title, then its skeleton', async () => {
    renderPage('Resources/Index', orders('detail'), { url: '/lathe/resources/orders' });

    fireEvent.click(screen.getByText('ORD-2'));
    await act(() => vi.advanceTimersByTimeAsync(110));

    expect(screen.getByRole('heading', { name: 'ORD-2' })).toBeTruthy();
    expect(document.querySelectorAll('[data-skeleton-card]')).toHaveLength(3);
    expect(requests).toHaveLength(1);
  });

  it('opens an edit form the usual way, with no instant page', async () => {
    renderPage('Resources/Index', orders('edit'), { url: '/lathe/resources/orders' });

    fireEvent.click(screen.getByText('ORD-2'));
    await act(() => vi.advanceTimersByTimeAsync(110));

    expect(requests[0]?.url).toContain('/lathe/resources/orders/2/edit');
    expect(screen.getByText('ORD-1')).toBeTruthy();
  });
});
