import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { SharedProps } from '../types';
import { bracketed, renderPage, visibleStrings } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

const resource = {
  uriKey: 'orders',
  label: 'Orders',
  singularLabel: 'Order',
  perPageOptions: [25, 50, 100],
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
  },
};

const columns = [
  { key: 'number', label: 'Number', component: 'text' },
  { key: 'customer', label: 'Customer', component: 'text' },
  { key: 'status', label: 'Status', component: 'badge-field' },
  { key: 'total', label: 'Total', component: 'text' },
  { key: 'placed_at', label: 'Placed', component: 'text' },
];

function row(id: number) {
  const number = `ORD-${String(id).padStart(5, '0')}`;

  return {
    id,
    title: number,
    cells: {
      number: { value: number, display: number },
      customer: { value: id, display: `Customer ${String(id)}` },
      status: { value: 'paid', display: 'Paid' },
      total: { value: 1000 * id, display: `$${String(id)}.00` },
      placed_at: { value: '2026-10-01', display: 'Oct 1, 2026' },
    },
    // No ability, so the rows have no menu column and the test reads the data columns only.
    abilities: {},
  };
}

function rows(count: number, first = 1) {
  return Array.from({ length: count }, (_, index) => row(first + index));
}

interface Options {
  rows?: ReturnType<typeof rows>;
  state?: { page: number; perPage: number; hasMorePages: boolean };
  count?: { value: number; exact: boolean; capped: boolean; estimated: boolean } | null | undefined;
  tableStyle?: SharedProps['tableStyle'];
  page?: Record<string, unknown>;
}

function renderOrders(options: Options = {}) {
  const props: Record<string, unknown> = {
    resource,
    table: { columns, rows: options.rows ?? rows(25), rowKey: 'id' },
    state: options.state ?? { page: 1, perPage: 25, hasMorePages: true },
  };

  if (!('count' in options)) {
    props.count = { value: 10000, exact: false, capped: false, estimated: false };
  } else if (options.count !== undefined) {
    props.count = options.count;
  }

  return renderPage('Resources/Index', props, {
    url: '/lathe/resources/orders',
    shared: options.tableStyle === undefined ? {} : { tableStyle: options.tableStyle },
    page: options.page,
  });
}

function bodyRows(): HTMLElement[] {
  const [, body] = screen.getAllByRole('rowgroup');

  return within(body as HTMLElement).queryAllByRole('row');
}

describe('orders table', () => {
  it('shows the columns and the rows as plain text', () => {
    renderOrders();

    // The first column holds the row checkboxes.
    expect(screen.getAllByRole('columnheader').map((cell) => cell.textContent)).toEqual([
      '',
      'Number',
      'Customer',
      'Status',
      'Total',
      'Placed',
    ]);
    expect(bodyRows()).toHaveLength(25);
    expect(within(bodyRows()[0] as HTMLElement).getAllByRole('cell').map((cell) => cell.textContent)).toEqual([
      '',
      'ORD-00001',
      'Customer 1',
      'Paid',
      '$1.00',
      'Oct 1, 2026',
    ]);
  });

  it('renders a display string that looks like HTML as text', () => {
    const tricky = row(1);
    tricky.cells.status = { value: 'x', display: '<b>bold</b>' };
    renderOrders({ rows: [tricky] });

    const cell = screen.getByText('<b>bold</b>');

    expect(cell.querySelector('b')).toBeNull();
  });

  it('counts the rows in the footer', () => {
    renderOrders();

    expect(screen.getByText('Showing 1 to 25 of 10,000')).toBeTruthy();
  });

  it('adds a plus to a capped count', () => {
    renderOrders({ count: { value: 10000, exact: false, capped: true, estimated: false } });

    expect(screen.getByText('Showing 1 to 25 of 10,000+')).toBeTruthy();
  });

  it('shows an estimate as a rounded figure with a tilde', () => {
    renderOrders({ count: { value: 9_812_345, exact: false, capped: false, estimated: true } });

    expect(screen.getByText('Showing 1 to 25 of ~9.8M')).toBeTruthy();
  });

  it('shows the range alone for a Resource that counts nothing', () => {
    renderOrders({ count: null });

    expect(screen.getByText('Showing 1 to 25')).toBeTruthy();
  });

  it('counts from the current page', () => {
    renderOrders({ rows: rows(25, 26), state: { page: 2, perPage: 25, hasMorePages: true } });

    expect(screen.getByText('Showing 26 to 50 of 10,000')).toBeTruthy();
  });

  it('shows the range alone while the count loads', () => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderOrders({ count: undefined, page: { deferredProps: { default: ['count'] } } });

    expect(screen.getByText('Showing 1 to 25')).toBeTruthy();
  });

  it('puts an em dash in place of a count that failed and keeps the rows', () => {
    renderOrders({ count: undefined, page: { rescuedProps: ['count'] } });

    expect(screen.getByText('Showing 1 to 25 of —')).toBeTruthy();
    expect(bodyRows()).toHaveLength(25);
  });

  it('shows the empty state without a create button', () => {
    renderOrders({ rows: [], count: { value: 0, exact: false, capped: false, estimated: false }, state: { page: 1, perPage: 25, hasMorePages: false } });

    expect(screen.getByText('No orders yet')).toBeTruthy();
    expect(screen.queryByRole('table')).toBeNull();
    expect(screen.queryByRole('link', { name: /create/i })).toBeNull();
    expect(screen.queryByRole('button', { name: /create/i })).toBeNull();
  });

  it.each([
    ['compact', 'h-8'],
    ['comfortable', 'h-10'],
    ['spacious', 'h-12'],
  ] as const)('gives %s rows the %s height', (density, height) => {
    renderOrders({ tableStyle: { density, striped: false } });

    expect(bodyRows().every((element) => element.classList.contains(height))).toBe(true);
  });

  it('alternates the row background when striped', () => {
    const { unmount } = renderOrders({ tableStyle: { density: 'comfortable', striped: true } });

    expect(bodyRows()[0]?.classList.contains('even:bg-muted/50')).toBe(true);
    unmount();

    renderOrders();

    expect(bodyRows()[0]?.classList.contains('even:bg-muted/50')).toBe(false);
  });

  it('scrolls sideways inside its card and keeps the first column', () => {
    renderOrders();

    const scroller = screen.getByRole('region', { name: 'Orders table' });
    expect(scroller.classList.contains('overflow-x-auto')).toBe(true);
    // A keyboard user can reach the sideways scroll.
    expect(scroller.tabIndex).toBe(0);
    expect(screen.getByRole('table').parentElement).toBe(scroller);
    expect(screen.getAllByRole('columnheader')[0]?.className).toContain('sticky start-0');
    expect(within(bodyRows()[0] as HTMLElement).getAllByRole('cell')[0]?.className).toContain('sticky start-0');
  });

  it('makes no request while 100 rows render', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const open = vi.spyOn(XMLHttpRequest.prototype, 'open');
    renderOrders({ rows: rows(100), state: { page: 1, perPage: 100, hasMorePages: true } });

    expect(bodyRows()).toHaveLength(100);
    expect(fetch).not.toHaveBeenCalled();
    expect(open).not.toHaveBeenCalled();
  });
});

describe('orders pagination', () => {
  it('visits the next page and leaves the default page size out of the URL', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders();

    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Previous' }).disabled).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders');
    expect(visit.mock.calls[0]?.[1]).toMatchObject({
      method: 'get',
      data: { page: 2 },
      preserveState: true,
      preserveScroll: true,
    });
  });

  it('visits the previous page', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ rows: rows(25, 26), state: { page: 2, perPage: 25, hasMorePages: false } });

    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Next' }).disabled).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Previous' }));

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: {} });
  });

  it('changes the page size from the resource options and starts at page 1', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderOrders({ rows: rows(25, 26), state: { page: 2, perPage: 25, hasMorePages: true } });

    const select = screen.getByLabelText<HTMLSelectElement>('Per page');

    expect([...select.options].map((option) => option.value)).toEqual(['25', '50', '100']);
    expect(select.value).toBe('25');

    fireEvent.change(select, { target: { value: '50' } });

    expect(visit.mock.calls[0]?.[1]).toMatchObject({ data: { per_page: 50 } });
  });

  it('shows a skeleton of the page size for at least 300 ms while a page loads', () => {
    vi.useFakeTimers();
    let finish: () => void = () => undefined;
    vi.spyOn(router, 'visit').mockImplementation((_url, options) => {
      const callbacks = options as { onStart?: () => void; onFinish?: () => void };
      callbacks.onStart?.();
      finish = () => callbacks.onFinish?.();
    });
    renderOrders();

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));

    expect(document.querySelectorAll('tbody tr[data-skeleton]')).toHaveLength(25);

    act(() => {
      vi.advanceTimersByTime(100);
      finish();
      vi.advanceTimersByTime(199);
    });

    expect(document.querySelectorAll('tbody tr[data-skeleton]')).toHaveLength(25);

    act(() => {
      vi.advanceTimersByTime(1);
    });

    expect(document.querySelectorAll('tbody tr[data-skeleton]')).toHaveLength(0);
    expect(bodyRows()).toHaveLength(25);
  });
});

describe('orders translation', () => {
  it('sends every string of the page through the translator', () => {
    __panel.setTranslator(bracketed);
    renderOrders();

    const data = new Set([
      'Orders',
      'Number',
      'Customer',
      'Status',
      'Total',
      'Placed',
      '25',
      '50',
      '100',
      'Paid',
      'Oct 1, 2026',
      ...rows(25).flatMap((item) => [item.cells.number.display, item.cells.customer.display, item.cells.total.display]),
    ]);
    const main = screen.getByRole('main');
    const strings = visibleStrings(main);

    expect(strings).toContain('[Showing 1 to 25 of 10,000]');
    expect(strings).toContain('[Next]');
    expect(strings.filter((text) => !data.has(text) && !/^\[.+\]$/.test(text))).toEqual([]);
  });

  it('sends the empty state through the translator', () => {
    __panel.setTranslator(bracketed);
    renderOrders({ rows: [], count: { value: 0, exact: false, capped: false, estimated: false }, state: { page: 1, perPage: 25, hasMorePages: false } });

    expect(screen.getByText('[No orders yet]')).toBeTruthy();
  });
});
