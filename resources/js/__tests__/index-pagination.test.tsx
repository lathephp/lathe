import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi, type MockInstance } from 'vitest';
import { choose, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
  localStorage.clear();
  Object.defineProperty(document, 'hidden', { value: false, configurable: true });
});

const columns = [
  { key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' },
  { key: 'total', label: 'Total', component: 'text', sortable: false, sortKey: 'total' },
];

function row(id: number) {
  const number = `ORD-${String(id).padStart(5, '0')}`;

  return {
    id,
    title: number,
    cells: { number: { value: number, display: number }, total: { value: id, display: `$${String(id)}.00` } },
    abilities: { view: true },
  };
}

function rows(count: number, first = 1) {
  return Array.from({ length: count }, (_, index) => row(first + index));
}

interface Options {
  definition?: Record<string, unknown>;
  rows?: ReturnType<typeof rows>;
  state?: { page: number; perPage: number; hasMorePages: boolean };
  count?: { value: number; exact: boolean; capped: boolean; estimated: boolean } | null;
  url?: string;
}

function renderOrders(options: Options = {}) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25, 50, 100],
        searchable: false,
        abilities: { create: false },
        definition: {
          debounce: 0.5,
          paginationType: 'links',
          clickAction: 'detail',
          polling: null,
          showPollingToggle: false,
          tableStyle: 'default',
          showColumnBorders: false,
          softDeletes: false,
          ...options.definition,
        },
      },
      table: { columns, sortKeys: [], rows: options.rows ?? rows(25), rowKey: 'id' },
      state: { sort: '', ...(options.state ?? { page: 1, perPage: 25, hasMorePages: true }) },
      count: options.count === undefined ? { value: 10000, exact: false, capped: false, estimated: false } : options.count,
    },
    { url: options.url ?? '/lathe/resources/orders' },
  );
}

function bodyRows(): HTMLElement[] {
  const [, body] = screen.getAllByRole('rowgroup');

  return within(body as HTMLElement).queryAllByRole('row');
}

describe('links pagination', () => {
  it('reads the range and the count with previous and next', () => {
    renderOrders();

    expect(screen.getByText('1–25 of 10,000')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Previous page' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Next page' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Load more' })).toBeNull();
  });

  it('adds first, last and page numbers from the count', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ count: { value: 10000, exact: true, capped: false, estimated: false } });

    expect(screen.getByRole('button', { name: 'First page' })).toBeTruthy();
    expect(screen.getByRole('button', { name: '1' }).getAttribute('aria-current')).toBe('page');

    fireEvent.click(screen.getByRole('button', { name: 'Last page' }));
    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { page: 400 } });
    expect(screen.getByRole('button', { name: '400' })).toBeTruthy();
  });

  it('goes back to the first page', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ rows: rows(25, 51), state: { page: 3, perPage: 25, hasMorePages: true } });

    fireEvent.click(screen.getByRole('button', { name: 'First page' }));

    // The first page is the URL without a page parameter.
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: {} });
  });

  it('hides last and its number when the count is capped', () => {
    renderOrders({ count: { value: 10000, exact: false, capped: true, estimated: false } });

    expect(screen.getByText('1–25 of 10,000+')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Last page' })).toBeNull();
    expect(screen.queryByRole('button', { name: '400' })).toBeNull();
  });

  it('numbers no page past the next one when the last page is unknown', () => {
    renderOrders({ count: { value: 10000, exact: false, capped: true, estimated: false }, state: { page: 4, perPage: 25, hasMorePages: false } });

    expect(screen.getByRole('button', { name: '4' }).getAttribute('aria-current')).toBe('page');
    expect(screen.queryByRole('button', { name: '5' })).toBeNull();
    // 1 2 3 4, with no gap after the last page there is.
    expect(screen.queryAllByText('…')).toHaveLength(0);
  });

  it('reads 50 rows per page from the count', () => {
    renderOrders({ rows: rows(50), state: { page: 1, perPage: 50, hasMorePages: true } });

    expect(screen.getByText('1–50 of 10,000')).toBeTruthy();
  });
});

describe('simple pagination', () => {
  it('shows previous and next with no count', () => {
    renderOrders({ definition: { paginationType: 'simple' } });

    expect(screen.getByText('1–25')).toBeTruthy();
    expect(screen.queryByText(/10,000/)).toBeNull();
    expect(screen.queryByRole('button', { name: 'Last page' })).toBeNull();
    expect(screen.getByRole('button', { name: 'Previous page' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Next page' })).toBeTruthy();
  });

  it('visits the next page', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ definition: { paginationType: 'simple' } });

    fireEvent.click(screen.getByRole('button', { name: 'Next page' }));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { page: 2 } });
  });
});

describe('load-more pagination', () => {
  it('appends the next page below the rows and keeps the URL', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      Response.json({
        table: { columns, sortKeys: [], rows: rows(25, 26), rowKey: 'id' },
        state: { page: 2, perPage: 25, hasMorePages: false, sort: '' },
      }),
    );
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ definition: { paginationType: 'load-more' }, url: '/lathe/resources/orders?sort=-total' });

    expect(screen.queryByRole('button', { name: 'Next page' })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Load more' }));

    await waitFor(() => {
      expect(bodyRows()).toHaveLength(50);
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/orders?sort=-total&page=2');
    expect(within(bodyRows()[25] as HTMLElement).getAllByRole('cell').map((cell) => cell.textContent)).toContain('ORD-00026');
    expect(screen.getByText('1–50 of 10,000')).toBeTruthy();
    // The last page has no next one.
    expect(screen.queryByRole('button', { name: 'Load more' })).toBeNull();
    expect(visit).not.toHaveBeenCalled();
  });

  it('loads nothing when the page scrolls to the bottom', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const reload = vi.spyOn(router, 'reload');
    renderOrders({ definition: { paginationType: 'load-more' } });

    fireEvent.scroll(window, { target: { scrollY: 10_000 } });
    fireEvent.scroll(screen.getByRole('region', { name: 'Orders table' }), { target: { scrollTop: 10_000 } });

    expect(fetch).not.toHaveBeenCalled();
    expect(reload).not.toHaveBeenCalled();
    expect(bodyRows()).toHaveLength(25);
  });
});

describe('per-page menu', () => {
  it('visits page 1 with the chosen size', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ url: '/lathe/resources/orders?page=3', rows: rows(25, 51), state: { page: 3, perPage: 25, hasMorePages: true } });

    await choose(screen.getByLabelText('Per page'), '100');

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { per_page: 100 } });
  });
});

describe('polling', () => {
  function tableReloads(reload: MockInstance<typeof router.reload>) {
    return reload.mock.calls.filter(([options]) => JSON.stringify(options).includes('"only":["table"]'));
  }

  it('reloads only the table every 10 seconds while the toggle is on', () => {
    vi.useFakeTimers();
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ definition: { polling: 10, showPollingToggle: true } });

    const toggle = screen.getByRole('switch', { name: 'Auto refresh' });
    expect(toggle.getAttribute('aria-checked')).toBe('true');

    act(() => {
      vi.advanceTimersByTime(9_999);
    });
    expect(tableReloads(reload)).toHaveLength(0);

    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(tableReloads(reload)).toHaveLength(1);
    expect(reload.mock.calls.at(-1)?.[0]).toMatchObject({ only: ['table'] });

    fireEvent.click(toggle);
    act(() => {
      vi.advanceTimersByTime(30_000);
    });

    expect(toggle.getAttribute('aria-checked')).toBe('false');
    expect(tableReloads(reload)).toHaveLength(1);
  });

  it('pauses while the browser tab is hidden', () => {
    vi.useFakeTimers();
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ definition: { polling: 10, showPollingToggle: true } });

    Object.defineProperty(document, 'hidden', { value: true, configurable: true });
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });
    act(() => {
      vi.advanceTimersByTime(30_000);
    });
    expect(tableReloads(reload)).toHaveLength(0);

    Object.defineProperty(document, 'hidden', { value: false, configurable: true });
    act(() => {
      document.dispatchEvent(new Event('visibilitychange'));
    });
    act(() => {
      vi.advanceTimersByTime(10_000);
    });
    expect(tableReloads(reload)).toHaveLength(1);
  });

  it('remembers the toggle per Resource', () => {
    vi.useFakeTimers();
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const { unmount } = renderOrders({ definition: { polling: 10, showPollingToggle: true } });

    fireEvent.click(screen.getByRole('switch', { name: 'Auto refresh' }));
    expect(localStorage.getItem('lathe-polling:orders')).toBe('off');
    unmount();

    renderOrders({ definition: { polling: 10, showPollingToggle: true } });
    act(() => {
      vi.advanceTimersByTime(30_000);
    });

    expect(screen.getByRole('switch', { name: 'Auto refresh' }).getAttribute('aria-checked')).toBe('false');
    expect(tableReloads(reload)).toHaveLength(0);
  });

  it('polls without a toggle when the Resource shows none', () => {
    vi.useFakeTimers();
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ definition: { polling: 5 } });

    act(() => {
      vi.advanceTimersByTime(10_000);
    });

    expect(screen.queryByRole('switch', { name: 'Auto refresh' })).toBeNull();
    expect(tableReloads(reload)).toHaveLength(2);
  });
});

describe('cells', () => {
  it('renders a one-line cell as text in a truncating td, with no element of its own', () => {
    renderOrders({ rows: rows(3) });
    const cell = within(bodyRows()[0] as HTMLElement).getByText('ORD-00001');

    // 100 rows of 20 columns would mount 2,000 more elements on every render (budget B5).
    expect(cell.tagName).toBe('TD');
    expect(cell.childElementCount).toBe(0);
    expect(cell.className).toContain('truncate');
  });
});
