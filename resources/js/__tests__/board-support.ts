import { vi } from 'vitest';
import { definition, urlOf } from './preview-support';

// The Orders list on a Board over status, and a fake server for the Board's column and count requests.

export const status = {
  key: 'status',
  attribute: 'status',
  label: 'Status',
  component: 'select-field',
  sortable: false,
  sortKey: 'status',
  inline: true,
  nullable: false,
  options: [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'cancelled', label: 'Cancelled' },
  ],
  badges: { pending: 'warning', paid: 'info', shipped: 'success', cancelled: 'neutral' },
  meter: ['pending', 'paid', 'shipped'],
};

const columns = [
  { key: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number' },
  { key: 'customer', label: 'Customer', component: 'belongs-to-field', sortable: false, sortKey: 'customer' },
  status,
  { key: 'total_cents', label: 'Total', component: 'text-field', sortable: false, sortKey: 'total_cents' },
];

/** One order as the index and the Board's column pages send it. */
export function order(id: number | string, value: string | null = 'pending') {
  const number = `ORD-${String(id)}`;

  return {
    id,
    title: number,
    cells: {
      number: { value: number, display: number },
      customer: { value: { value: 3, display: 'Gerard Reichert', href: '/lathe/resources/customers/3' }, display: 'Gerard Reichert' },
      status: { value, display: value ?? '' },
      total_cents: { value: 16498, display: '$164.98' },
    },
    abilities: { view: true, update: true, inlineEdit: true },
    updatedAt: '2026-10-14T09:00:00.000Z',
  };
}

export function orders(table: Record<string, unknown> = {}, extra: Record<string, unknown> = {}) {
  return {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
    table: {
      columns,
      rows: [order(1), order(2, 'paid')],
      rowKey: 'id',
      sortKeys: [],
      phoneCard: { title: 'number', badge: 'status', meta: 'customer', value: 'total_cents' },
      board: { field: status, noValue: false },
      ...table,
    },
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    count: { value: 10000, exact: false, capped: true, estimated: false },
    ...extra,
  };
}

export interface BoardServer {
  /** Every request URL the page sent through fetch, in order. */
  asked: string[];
  /** The PUT bodies, by URL. */
  puts: { url: string; body: unknown }[];
}

/**
 * Answers the Board's requests: 25 orders per column page, more after page 1 only, nothing in
 * the columns named `empty`, and the counts given; a PUT answers 204.
 */
export function boardServer(options: { empty?: string[]; counts?: Record<string, number | 'capped'> } = {}): BoardServer {
  const server: BoardServer = { asked: [], puts: [] };

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = urlOf(input);
    server.asked.push(url);

    if (init?.method === 'PUT') {
      server.puts.push({ url, body: JSON.parse(init.body as string) });

      return Promise.resolve(new Response(null, { status: 204 }));
    }

    const params = new URL(url, 'http://localhost').searchParams;

    if (/\/board\/counts\?|\/board\/counts$/.test(url)) {
      const counts = Object.entries(options.counts ?? {}).map(([value, count]) => ({
        value: value === '' ? null : value,
        count: count === 'capped' ? { value: 10000, exact: false, capped: true, estimated: false } : { value: count, exact: true, capped: false, estimated: false },
      }));

      return Promise.resolve(new Response(JSON.stringify({ columns: counts }), { status: 200 }));
    }

    if (/\/board\?/.test(url)) {
      const column = params.get('column') ?? '';
      const page = Number(params.get('offset')) / 25 + 1;
      const rows = (options.empty ?? []).includes(column) ? [] : Array.from({ length: 25 }, (_, index) => order(`${column || 'none'}-${String(page)}-${String(index + 1)}`, column || null));

      return Promise.resolve(new Response(JSON.stringify({ rows, hasMore: rows.length > 0 && page === 1 }), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  return server;
}
