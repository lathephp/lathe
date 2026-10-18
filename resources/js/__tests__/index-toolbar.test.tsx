import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

const columns = [
  { key: 'number', label: 'Number', component: 'text', sortable: true, sortKey: 'number' },
  { key: 'customer', label: 'Customer', component: 'text', sortable: true, sortKey: 'customer.name' },
  { key: 'status', label: 'Status', component: 'text', sortable: false, sortKey: 'status' },
  { key: 'total_cents', label: 'Total', component: 'text', sortable: true, sortKey: 'total_cents' },
  { key: 'placed_at', label: 'Placed', component: 'text', sortable: true, sortKey: 'placed_at' },
];

function row(id: number) {
  return {
    id,
    title: `ORD-${String(id)}`,
    cells: {
      number: { value: `ORD-${String(id)}`, display: `ORD-${String(id)}` },
      total_cents: { value: id, display: String(id) },
    },
    abilities: { view: true },
  };
}

interface Options {
  url?: string;
  rows?: ReturnType<typeof row>[];
  create?: boolean;
  error?: { key: string; message: string };
}

function renderOrders(options: Options = {}) {
  const props: Record<string, unknown> = {
    resource: {
      uriKey: 'orders',
      label: 'Orders',
      singularLabel: 'Order',
      perPageOptions: [25, 50, 100],
      searchable: true,
      abilities: { create: options.create ?? false },
      definition: { debounce: 0.5 },
    },
    // Customer sorts through a relation this Resource does not offer, so its key is left out.
    table: { columns, sortKeys: ['number', 'total_cents', 'placed_at'], rows: options.rows ?? [row(1), row(2)], rowKey: 'id' },
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    count: null,
  };

  if (options.error !== undefined) {
    props.error = options.error;
  }

  return renderPage('Resources/Index', props, { url: options.url ?? '/lathe/resources/orders' });
}

function mockVisit() {
  return vi.spyOn(router, 'visit').mockImplementation(() => undefined);
}

function header(name: string): HTMLElement {
  return screen.getAllByRole('columnheader').find((cell) => cell.textContent === name) as HTMLElement;
}

describe('toolbar search', () => {
  it('opens the box at 260 px on a wide screen and to its icon on a narrow one', () => {
    renderOrders();
    const box = screen.getByRole('searchbox');

    expect(box.className).toContain('w-[260px]');
    expect(box.className).toContain('max-md:w-9');
  });

  it('waits 500 ms after the last keystroke, then visits once with q trimmed', () => {
    vi.useFakeTimers();
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?page=3' });
    const box = screen.getByRole('searchbox', { name: 'Search orders' });

    fireEvent.change(box, { target: { value: 'ORD' } });
    act(() => {
      vi.advanceTimersByTime(300);
    });
    fireEvent.change(box, { target: { value: '  ORD-1 ' } });
    act(() => {
      vi.advanceTimersByTime(499);
    });

    expect(visit).not.toHaveBeenCalled();

    act(() => {
      vi.advanceTimersByTime(1);
    });

    expect(visit).toHaveBeenCalledTimes(1);
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({ method: 'get', data: { q: 'ORD-1' } });
  });

  it('removes q when the box is emptied', () => {
    vi.useFakeTimers();
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?q=ORD&sort=number' });
    const box = screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' });

    expect(box.value).toBe('ORD');

    fireEvent.change(box, { target: { value: '  ' } });
    act(() => {
      vi.advanceTimersByTime(500);
    });

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { sort: 'number' } });
    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).not.toHaveProperty('q');
  });

  it('focuses the box on / and leaves another input alone', () => {
    renderOrders();
    const box = screen.getByRole('searchbox', { name: 'Search orders' });

    fireEvent.keyDown(document.body, { key: '/' });

    expect(document.activeElement).toBe(box);

    const other = document.createElement('input');
    document.body.append(other);
    other.focus();
    fireEvent.keyDown(other, { key: '/' });

    expect(document.activeElement).toBe(other);
    other.remove();
  });

  it('stops answering / once the page is gone', () => {
    const { unmount } = renderOrders();
    unmount();
    const other = document.createElement('button');
    document.body.append(other);
    other.focus();

    fireEvent.keyDown(document.body, { key: '/' });

    expect(document.activeElement).toBe(other);
    other.remove();
  });
});

describe('search box and the URL', () => {
  it('keeps a trailing space the person is still typing when its search lands', async () => {
    vi.spyOn(router, 'visit').mockImplementation((_url, options) => {
      const data = (options as { data: Record<string, string> }).data;
      router.replace({ url: `/lathe/resources/orders?${new URLSearchParams(data).toString()}`, preserveState: true });
    });
    renderOrders();
    const box = screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' });

    fireEvent.change(box, { target: { value: 'ORD ' } });

    await vi.waitFor(() => {
      expect(window.location.search).toBe('?q=ORD');
    });
    expect(box.value).toBe('ORD ');
  });
});

describe('sort headers', () => {
  it('shows an unsorted column\'s arrow only under the pointer, and the sorted one\'s always', () => {
    renderOrders({ url: '/lathe/resources/orders?sort=number' });
    const arrow = (name: string) => header(name).querySelector('svg')?.getAttribute('class') ?? '';

    expect(arrow('Total')).toContain('opacity-0');
    expect(arrow('Total')).toContain('group-hover/sort:opacity-50');
    expect(arrow('Number')).not.toContain('opacity-0');
  });

  it('sorts Total ascending on the first click and descending on the second', () => {
    const visit = mockVisit();
    const { unmount } = renderOrders();

    expect(header('Total').getAttribute('aria-sort')).toBe('none');

    fireEvent.click(within(header('Total')).getByRole('button', { name: 'Total' }));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { sort: 'total_cents' } });
    unmount();

    renderOrders({ url: '/lathe/resources/orders?sort=total_cents' });

    expect(header('Total').getAttribute('aria-sort')).toBe('ascending');

    fireEvent.click(within(header('Total')).getByRole('button', { name: 'Total' }));

    expect(visit.mock.calls[1]?.[1]).toMatchObject({ data: { sort: '-total_cents' } });
  });

  it('turns the sort off on the third click and resets the page', () => {
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?sort=-total_cents&page=3' });

    expect(header('Total').getAttribute('aria-sort')).toBe('descending');

    fireEvent.click(within(header('Total')).getByRole('button', { name: 'Total' }));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({});
  });

  it('replaces the sort on a plain click and appends a column on shift-click', () => {
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?sort=-total_cents' });

    fireEvent.click(within(header('Placed')).getByRole('button', { name: 'Placed' }), { shiftKey: true });
    fireEvent.click(within(header('Placed')).getByRole('button', { name: 'Placed' }));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { sort: '-total_cents,placed_at' } });
    expect(visit.mock.calls[1]?.[1]).toMatchObject({ data: { sort: 'placed_at' } });
  });

  it('draws no sort control for a column outside sortKeys', () => {
    renderOrders({ url: '/lathe/resources/orders?sort=customer.name' });

    for (const name of ['Status', 'Customer']) {
      expect(within(header(name)).queryByRole('button')).toBeNull();
      expect(header(name).hasAttribute('aria-sort')).toBe(false);
    }
  });
});

describe('index banner', () => {
  it('shows the error message with no rows and resets to the bare index URL', () => {
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?sort=password&q=ann', rows: [], error: { key: 'sort', message: 'sort is invalid' } });

    expect(screen.getByRole('alert').textContent).toContain('sort is invalid');
    expect(screen.queryByRole('table')).toBeNull();
    expect(screen.queryByText('No orders yet')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Reset' }));

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders');
    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({});
  });
});

describe('filtered empty state', () => {
  it('clears q and every filter and keeps the sort', () => {
    const visit = mockVisit();
    renderOrders({ url: '/lathe/resources/orders?q=zzzz&filter[status]=paid&sort=-total_cents', rows: [] });

    expect(screen.getByText('No results match your filters')).toBeTruthy();
    expect(screen.queryByText('No orders yet')).toBeNull();
    expect(screen.queryByRole('link', { name: 'Create' })).toBeNull();

    // The empty state's button, not the one that ends the chips row.
    const empty = screen.getByText('No results match your filters').parentElement as HTMLElement;
    fireEvent.click(within(empty).getByRole('button', { name: 'Clear filters' }));

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ sort: '-total_cents' });
  });

  it('empties the search box once the cleared URL lands', () => {
    // The visit lands at once with the URL it asked for, keeping the page's state as a real one does.
    const visit = vi.spyOn(router, 'visit').mockImplementation((_url, options) => {
      const data = (options as { data: Record<string, string> }).data;
      router.replace({ url: `/lathe/resources/orders?${new URLSearchParams(data).toString()}`, preserveState: true });
    });
    renderOrders({ url: '/lathe/resources/orders?q=zzzz', rows: [] });

    expect(screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' }).value).toBe('zzzz');

    fireEvent.click(screen.getByRole('button', { name: 'Clear filters' }));

    expect(visit).toHaveBeenCalledTimes(1);
    return vi.waitFor(() => {
      expect(screen.getByRole<HTMLInputElement>('searchbox', { name: 'Search orders' }).value).toBe('');
    });
  });

  it('links to the create page when the user may create', () => {
    renderOrders({ url: '/lathe/resources/orders?q=zzzz', rows: [], create: true });

    expect(screen.getByRole('link', { name: 'Create' }).getAttribute('href')).toBe('/lathe/resources/orders/new');
  });

  it('keeps the plain empty state without q or a filter', () => {
    renderOrders({ rows: [], create: true });

    expect(screen.getByText('No orders yet')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Clear filters' })).toBeNull();
  });

  it('puts the title on a row of its own above the card, with the controls in the card\'s first row', () => {
    renderOrders();
    const card = document.querySelector('[data-lathe-card]');
    const toolbar = document.querySelector('[data-lathe-toolbar]');

    expect(document.querySelector('[data-lathe-header] h1')?.textContent).toBe('Orders');
    expect(card?.contains(document.querySelector('[data-lathe-header]'))).toBe(false);
    expect(toolbar?.parentElement).toBe(card);
    expect(toolbar?.querySelector('input[type="search"]')).toBeTruthy();
  });
});
