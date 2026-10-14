import { queryClient } from '@lathephp/core';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { LayoutBlock } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  queryClient.clear();
  vi.restoreAllMocks();
});

const resource = { uriKey: 'products', label: 'Products', singularLabel: 'Product', abilities: { create: true } };

const tagsField = {
  key: 'tags',
  attribute: 'tags',
  label: 'Tags',
  component: 'morph-to-many-field',
  sortable: false,
  computed: false,
  nullable: false,
  stacked: false,
  fullWidth: false,
  columnSpan: null,
  indexWidth: null,
  borderless: false,
  compact: false,
  textAlign: 'left',
  placeholder: null,
  help: null,
  helpWidth: null,
  wrapping: false,
  section: null,
  autocomplete: null,
  meta: {},
  collapsible: false,
  collapsed: false,
  perPageOptions: null,
  withCount: false,
  kind: 'morphToMany',
  relationship: 'tags',
  resource: 'tags',
  singularLabel: 'Tag',
  pluralLabel: 'Tags',
};

function renderProduct() {
  return renderPage('Resources/Detail', {
    resource,
    record: {
      id: 1,
      title: 'Desk lamp',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [{ type: 'relation-field', key: 'tags', field: tagsField } as LayoutBlock],
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
    },
    breadcrumbs: [],
  });
}

const columns = [
  { key: 'name', attribute: 'name', label: 'Name', component: 'text-field', sortable: false, sortKey: 'name', textAlign: 'left' },
  { key: 'position', attribute: 'position', label: 'Position', component: 'number-field', sortable: true, sortKey: 'position', textAlign: 'right' },
];

function row(relatedId: number, name: string, position: number, pivotId?: number) {
  return {
    id: pivotId ?? relatedId,
    title: name,
    cells: { name: { value: name, display: name }, position: { value: position, display: String(position) } },
    abilities: { view: true },
    related_id: relatedId,
    ...(pivotId === undefined ? {} : { pivot_id: pivotId }),
  };
}

function positionField(value: unknown) {
  return { ...tagsField, key: 'position', attribute: 'position', label: 'Position', component: 'number-field', kind: undefined, required: true, min: 0, value };
}

interface Call {
  url: string;
  method: string;
  body: unknown;
}

/** Answers the panel, the picker, the pivot forms and the writes, and records every call. */
function server({
  attachAny = true,
  mayAttach = true,
  mayDetach = true,
  attach = () => new Response(JSON.stringify({ attached: true }), { status: 201 }),
}: { attachAny?: boolean; mayAttach?: boolean; mayDetach?: boolean; attach?: () => Response } = {}) {
  const calls: Call[] = [];

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const method = init?.method ?? 'GET';
    calls.push({ url, method, body: typeof init?.body === 'string' ? JSON.parse(init.body) : undefined });
    const json = (body: unknown) => Promise.resolve(new Response(JSON.stringify(body), { status: 200 }));

    if (method === 'POST') {
      return Promise.resolve(attach());
    }

    if (method === 'PUT') {
      return json({ updated: true });
    }

    if (method === 'DELETE') {
      return json({ detached: true });
    }

    if (url.includes('/relate-authorization')) {
      return json({ tags: { add: false, attach: mayAttach, attachAny, detach: mayDetach } });
    }

    if (url.includes('/filters')) {
      return json({ filters: [] });
    }

    if (url.includes('/associatable')) {
      return json({ items: [{ value: 9, display: 'sale' }, { value: 10, display: 'summer' }], has_more: false });
    }

    if (url.includes('/tags/5/pivot-fields')) {
      return json({ fields: [positionField(2)] });
    }

    if (url.includes('/pivot-fields')) {
      return json({ fields: [positionField(null)] });
    }

    return json({
      columns,
      sortKeys: ['name', 'position'],
      rows: [row(5, 'new', 2, 70), row(6, 'hot', 4)],
      count: null,
      page: 1,
      perPage: 25,
      perPageOptions: [25, 50, 100],
      hasMore: false,
      pivotActions: ['Tag Actions'],
    });
  });

  return {
    calls,
    asked: (part: string, method = 'GET') => calls.filter((call) => call.method === method && call.url.includes(part)),
    listed: () => calls.filter((call) => call.method === 'GET' && /\/products\/1\/tags(\?|$)/.test(call.url)).length,
  };
}

async function settle() {
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 20));
  });
}

async function openAttach() {
  fireEvent.click(await screen.findByRole('button', { name: 'Attach' }));
  const dialog = await screen.findByRole('dialog');
  await within(dialog).findByRole('spinbutton', { name: 'Position' });

  return dialog;
}

async function pick(dialog: HTMLElement, name: string) {
  fireEvent.click(within(dialog).getByRole('button', { name: 'Tag' }));
  fireEvent.click(await within(dialog).findByRole('option', { name }));
}

function rowOf(name: string): HTMLElement {
  const cell = screen.getByRole('cell', { name });

  return cell.closest('tr') as HTMLElement;
}

describe('attaching to a pivot relation', () => {
  it('shows Attach only when the user may attach any record', async () => {
    server({ attachAny: false });
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    await settle();

    expect(screen.queryByRole('button', { name: 'Attach' })).toBeNull();
  });

  it('loads the pivot form once, the picker on open, and attaches the picked record', async () => {
    const api = server();
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    const before = api.listed();
    const dialog = await openAttach();

    expect(api.asked('/products/1/tags/pivot-fields')).toHaveLength(1);
    expect(api.asked('/associatable')).toHaveLength(0);

    await pick(dialog, 'sale');

    expect(api.asked('/associatable')[0]?.url).toBe('/lathe/api/resources/products/1/tags/associatable?q=&page=1');

    fireEvent.change(within(dialog).getByRole('spinbutton', { name: 'Position' }), { target: { value: '1' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Attach' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(api.asked('/products/1/tags', 'POST').map((call) => [call.url, call.body])).toEqual([['/lathe/api/resources/products/1/tags', { related_id: 9, pivot: { position: '1' } }]]);
    await waitFor(() => {
      expect(api.listed()).toBe(before + 1);
    });
  });

  it('keeps the modal open with the picker cleared on Attach & attach another', async () => {
    const api = server();
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    const before = api.listed();
    const dialog = await openAttach();
    await pick(dialog, 'sale');
    fireEvent.change(within(dialog).getByRole('spinbutton', { name: 'Position' }), { target: { value: '1' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Attach & attach another' }));

    await waitFor(() => {
      expect(api.listed()).toBe(before + 1);
    });
    expect(api.asked('/products/1/tags', 'POST').map((call) => call.body)).toEqual([{ related_id: 9, pivot: { position: '1' } }]);
    expect(screen.getByRole('dialog')).toBe(dialog);
    expect(within(dialog).getByRole('button', { name: 'Tag' }).textContent).toBe('Select Tag');
    expect(within(dialog).getByRole('spinbutton', { name: 'Position' })).toHaveProperty('value', '');
    expect(api.asked('/pivot-fields')).toHaveLength(1);
  });

  it('shows a related_id error from the server in the modal', async () => {
    server({ attach: () => new Response(JSON.stringify({ message: 'Invalid.', errors: { related_id: ['This tag is already attached.'] } }), { status: 422 }) });
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    const dialog = await openAttach();
    await pick(dialog, 'sale');
    fireEvent.change(within(dialog).getByRole('spinbutton', { name: 'Position' }), { target: { value: '1' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Attach' }));

    expect(await within(dialog).findByText('This tag is already attached.')).toBeTruthy();
    expect(screen.getByRole('dialog')).toBe(dialog);
  });
});

describe('editing and detaching a pivot row', () => {
  it.each([
    { mayAttach: true, mayDetach: false, edits: 2, detaches: 0 },
    { mayAttach: false, mayDetach: true, edits: 0, detaches: 2 },
  ])('shows Edit pivot only with attach and Detach only with detach (attach $mayAttach, detach $mayDetach)', async ({ mayAttach, mayDetach, edits, detaches }) => {
    const api = server({ attachAny: false, mayAttach, mayDetach });
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    await waitFor(() => {
      expect(api.asked('/relate-authorization')).toHaveLength(1);
    });
    await settle();

    expect(screen.queryAllByRole('button', { name: 'Edit pivot' })).toHaveLength(edits);
    expect(screen.queryAllByRole('button', { name: 'Detach' })).toHaveLength(detaches);
  });

  it('edits the pivot of a row with its pivot id and refetches', async () => {
    const api = server();
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    const before = api.listed();
    fireEvent.click(within(rowOf('new')).getByRole('button', { name: 'Edit pivot' }));
    const dialog = await screen.findByRole('dialog');
    const position = await within(dialog).findByRole('spinbutton', { name: 'Position' });

    expect(api.asked('/pivot-fields').map((call) => call.url)).toEqual(['/lathe/api/resources/products/1/tags/5/pivot-fields?pivot_id=70']);
    expect(position).toHaveProperty('value', '2');

    fireEvent.change(position, { target: { value: '3' } });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Update' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });
    expect(api.asked('/pivot', 'PUT').map((call) => [call.url, call.body])).toEqual([['/lathe/api/resources/products/1/tags/5/pivot?pivot_id=70', { pivot: { position: '3' } }]]);
    await waitFor(() => {
      expect(api.listed()).toBe(before + 1);
    });
  });

  it('asks before it detaches, then deletes and refetches', async () => {
    const api = server();
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    const before = api.listed();
    fireEvent.click(within(rowOf('hot')).getByRole('button', { name: 'Detach' }));
    const dialog = await screen.findByRole('dialog');
    await settle();

    expect(api.asked('/tags/', 'DELETE')).toHaveLength(0);

    fireEvent.click(within(dialog).getByRole('button', { name: 'Detach' }));

    await waitFor(() => {
      expect(api.listed()).toBe(before + 1);
    });
    expect(api.asked('/tags/', 'DELETE').map((call) => call.url)).toEqual(['/lathe/api/resources/products/1/tags/6']);
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('sends the pivot id when it detaches a row that has one', async () => {
    const api = server();
    renderProduct();
    await screen.findByRole('cell', { name: 'new' });
    fireEvent.click(within(rowOf('new')).getByRole('button', { name: 'Detach' }));
    fireEvent.click(within(await screen.findByRole('dialog')).getByRole('button', { name: 'Detach' }));

    await waitFor(() => {
      expect(api.asked('/tags/', 'DELETE').map((call) => call.url)).toEqual(['/lathe/api/resources/products/1/tags/5?pivot_id=70']);
    });
  });
});
