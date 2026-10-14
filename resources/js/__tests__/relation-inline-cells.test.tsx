import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

const relation = { sortable: false, inline: true, searchable: true, debounce: 500, withTrashed: false, meta: {} };
const customer = { ...relation, key: 'customer', attribute: 'customer', relationship: 'customer', label: 'Customer', component: 'belongs-to-field', kind: 'belongsTo', resource: 'customers', singularLabel: 'Customer', sortKey: 'customer' };
const commentable = {
  ...relation,
  key: 'commentable',
  attribute: 'commentable',
  relationship: 'commentable',
  label: 'Commentable',
  component: 'morph-to-field',
  kind: 'morphTo',
  sortKey: 'commentable',
  types: [
    { value: 'posts', label: 'Post' },
    { value: 'products', label: 'Product' },
  ],
  defaultType: null,
};

function renderRows(uriKey: string, column: Record<string, unknown>, cell: unknown) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey, label: 'Rows', singularLabel: 'Row', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: {
        columns: [column],
        rows: [{ id: 12, title: 'Row 12', cells: { [String(column.key)]: cell }, abilities: { view: true, update: true }, updatedAt: '2026-10-14T09:00:00.000Z' }],
        rowKey: 'id',
        sortKeys: [],
      },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: `/lathe/resources/${uriKey}` },
  );
}

/** Answers lookups with `items` and the PATCH with `patch`, by method, and records every call. */
function server(items: unknown[], patch: unknown) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((_input, init) =>
    Promise.resolve(new Response(JSON.stringify(init?.method === 'PATCH' ? patch : { items, has_more: false }), { status: 200 })),
  );
}

function calls(fetch: { mock: { calls: unknown[][] } }) {
  return fetch.mock.calls.map(([input, init]) => {
    const options = init as RequestInit | undefined;

    return { url: typeof input === 'string' ? input : '', method: options?.method ?? 'GET', body: typeof options?.body === 'string' ? (JSON.parse(options.body) as unknown) : null };
  });
}

async function flush() {
  await act(async () => {
    for (let index = 0; index < 5; index++) {
      await Promise.resolve();
    }
  });
}

describe('relation inline cells', () => {
  it('opens a picker on a belongsTo cell and commits the chosen customer with one PATCH', async () => {
    const fetch = server([{ value: 4, display: 'Anna Park' }], {
      field: 'customer',
      fields: { customer: { value: { value: 4, display: 'Anna Park', href: '/lathe/resources/customers/4' }, display: 'Anna Park' } },
      updatedAt: '2026-10-14T09:00:02.000Z',
    });
    renderRows('orders', customer, { value: { value: 3, display: 'Ann Lee', href: '/lathe/resources/customers/3' }, display: 'Ann Lee' });

    fireEvent.click(screen.getByRole('button', { name: 'Customer' }));
    await flush();
    fireEvent.click(screen.getByRole('option', { name: 'Anna Park' }));
    await flush();

    expect(calls(fetch).filter((call) => call.method === 'PATCH')).toEqual([
      { url: '/lathe/api/resources/orders/12/field/customer', method: 'PATCH', body: { value: 4, _updated_at: '2026-10-14T09:00:00.000Z' } },
    ]);
    expect(calls(fetch)[0]?.url).toBe('/lathe/api/resources/orders/12/customer/associatable?q=&page=1');
    expect(screen.getByRole('button', { name: 'Customer' }).textContent).toBe('Anna Park');
  });

  it('commits a morphTo cell with its id and type once a record of the new type is picked', async () => {
    const fetch = server([{ value: 9, display: 'Desk lamp' }], {
      field: 'commentable',
      fields: { commentable: { value: { type: 'products', id: 9, display: 'Desk lamp', href: '/lathe/resources/products/9' }, display: 'Desk lamp' } },
      updatedAt: '2026-10-14T09:00:02.000Z',
    });
    renderRows('comments', commentable, { value: { type: 'posts', id: 1, display: 'Hello world', href: '/lathe/resources/posts/1' }, display: 'Hello world' });

    fireEvent.change(screen.getByRole('combobox', { name: 'Commentable' }), { target: { value: 'products' } });
    await flush();
    expect(fetch).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Select Product' }));
    await flush();
    fireEvent.click(screen.getByRole('option', { name: 'Desk lamp' }));
    await flush();

    expect(calls(fetch)[0]?.url).toBe('/lathe/api/resources/comments/12/commentable/associatable?type=products&q=&page=1');
    expect(calls(fetch).filter((call) => call.method === 'PATCH')).toEqual([
      { url: '/lathe/api/resources/comments/12/field/commentable', method: 'PATCH', body: { value: 9, type: 'products', _updated_at: '2026-10-14T09:00:00.000Z' } },
    ]);
    expect(screen.getByRole('button', { name: 'Desk lamp' })).toBeDefined();
  });
});
