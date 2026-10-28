import { fireEvent, screen, waitFor } from '@testing-library/react';
import { expect, vi } from 'vitest';

// The Orders list and the preview replies the preview panel's tests share.

export const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
// The workbench Orders mix: the title column, a belongsTo link, a badge select and a plain value.
const columns = [
  { key: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number' },
  { key: 'customer', label: 'Customer', component: 'belongs-to-field', sortable: false, sortKey: 'customer' },
  {
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
    ],
    badges: { pending: 'warning', paid: 'info' },
  },
  { key: 'total_cents', label: 'Total', component: 'text-field', sortable: false, sortKey: 'total_cents' },
];
const numbers = ['ORD-79194426', 'ORD-79186507', 'ORD-79178588', 'ORD-79170669', 'ORD-79162750', 'ORD-79154831', 'ORD-79146912'];

export function orders(extra: Record<string, unknown> = {}) {
  return {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], abilities: { create: false }, definition },
    table: {
      columns,
      rows: numbers.map((number, index) => ({
        id: index + 1,
        title: number,
        cells: {
          number: { value: number, display: number },
          customer: { value: { value: 3, display: 'Gerard Reichert', href: '/lathe/resources/customers/3' }, display: 'Gerard Reichert' },
          status: { value: 'pending', display: 'Pending' },
          total_cents: { value: 16498, display: '$164.98' },
        },
        abilities: { view: true, update: true, inlineEdit: true },
        updatedAt: '2026-10-14T09:00:00.000Z',
      })),
      rowKey: 'id',
      sortKeys: [],
      phoneCard: { title: 'number', badge: 'status', meta: 'customer', value: 'total_cents' },
      ...extra,
    },
    state: { page: 1, perPage: 25, hasMorePages: false },
  };
}

export function reply(id: number, status = 'Pending') {
  return {
    id,
    title: numbers[id - 1],
    subtitle: '',
    avatar: null,
    fields: [
      { key: 'number', label: 'Number', component: 'text-field', value: numbers[id - 1], display: numbers[id - 1] },
      { key: 'placed_at', label: 'Placed', component: 'text-field', value: '2026-10-18', display: 'Oct 18, 2026' },
      { key: 'state', label: 'State', component: 'text-field', value: status, display: status },
    ],
    abilities: { view: true, update: true },
    actions: { actions: [{ uriKey: 'mark-as-shipped', name: 'Mark as shipped', icon: null, description: null, group: null, destructive: false, standalone: false, withoutConfirmation: true, enabled: true }], pivotActions: [], counts: {} },
  };
}

export function urlOf(input: RequestInfo | URL): string {
  return typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
}

/** Answers each preview request with its order, and says which were asked for. */
export function previews(): string[] {
  const asked: string[] = [];
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = urlOf(input);
    const match = /\/resources\/orders\/(\d+)\/preview$/.exec(url);

    if (match !== null) {
      asked.push(url);

      return Promise.resolve(new Response(JSON.stringify(reply(Number(match[1]))), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  return asked;
}

export async function press(key: string) {
  await waitFor(() => {
    expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
  });
  fireEvent.keyDown(document.activeElement ?? document.body, { key });
}

export function firstCell(): HTMLElement {
  return screen.getByRole('grid').querySelector<HTMLElement>('tbody td[tabindex="0"]') as HTMLElement;
}

export function panel(): HTMLElement {
  return document.querySelector<HTMLElement>('[data-lathe-preview]') as HTMLElement;
}
