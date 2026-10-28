import { router } from '@inertiajs/react';
import { fireEvent, screen } from '@testing-library/react';
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
