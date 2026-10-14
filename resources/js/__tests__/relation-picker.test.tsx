import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerRelationFields } from '../fields/builtin/relation';
import { FormField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

registerRelationFields();

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'customer',
    attribute: 'customer',
    label: 'Customer',
    component: 'belongs-to-field',
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
    kind: 'belongsTo',
    relationship: 'customer',
    resource: 'customers',
    searchable: true,
    debounce: 500,
    withTrashed: false,
    singularLabel: 'Customer',
    pluralLabel: 'Customers',
    ...overrides,
  };
}

const anns = [
  { value: 3, display: 'Ann Lee', subtitle: 'ann@example.com' },
  { value: 4, display: 'Anna Park' },
];

function reply(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), { status });
}

function server(...replies: Response[]) {
  const fetch = vi.spyOn(globalThis, 'fetch');

  for (const next of replies) {
    fetch.mockResolvedValueOnce(next);
  }

  fetch.mockResolvedValue(reply({ items: anns, has_more: false }));

  return { fetch, urls: () => fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : input instanceof URL ? input.href : input.url)) };
}

function renderField(field: FieldSchema = schema(), onChange: (value: unknown) => void = () => undefined, value: unknown = null) {
  return render(
    <>
      <label htmlFor="field-customer">Customer</label>
      <FormField field={{ ...field, value }} value={value} onChange={onChange} id="field-customer" describedBy={undefined} invalid={false} disabled={false} />
    </>,
  );
}

async function flush() {
  await act(async () => {
    for (let index = 0; index < 5; index++) {
      await Promise.resolve();
    }
  });
}

async function advance(ms: number) {
  await act(async () => {
    vi.advanceTimersByTime(ms);
    for (let index = 0; index < 5; index++) {
      await Promise.resolve();
    }
  });
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('belongsTo relation picker', () => {
  it('sends nothing on render and one request when the picker opens', async () => {
    const { fetch, urls } = server();
    renderField();

    expect(fetch).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();

    expect(urls()).toEqual(['/lathe/api/resources/orders/associatable/customer?q=&page=1']);
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ann Leeann@example.com', 'Anna Park']);
  });

  it('waits 500 ms after typing before the next request', async () => {
    vi.useFakeTimers();
    const { urls } = server();
    renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'ann' } });
    await advance(499);

    expect(urls()).toHaveLength(1);

    await advance(1);

    expect(urls()).toEqual(['/lathe/api/resources/orders/associatable/customer?q=&page=1', '/lathe/api/resources/orders/associatable/customer?q=ann&page=1']);
  });

  it('waits the field debounce of 250 ms instead', async () => {
    vi.useFakeTimers();
    const { urls } = server();
    renderField(schema({ debounce: 250 }));

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'ann' } });
    await advance(249);

    expect(urls()).toHaveLength(1);

    await advance(1);

    expect(urls()[1]).toBe('/lathe/api/resources/orders/associatable/customer?q=ann&page=1');
  });

  it('calls onChange with the chosen value and display', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.click(screen.getByRole('option', { name: /Anna Park/ }));

    expect(onChange).toHaveBeenCalledWith({ value: 4, display: 'Anna Park' });
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('picks the highlighted option with the arrow keys and Enter', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'ArrowDown' });
    fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'ArrowDown' });
    fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'Enter' });

    expect(onChange).toHaveBeenCalledWith({ value: 4, display: 'Anna Park' });
  });

  it('closes on Escape', async () => {
    server();
    renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.keyDown(screen.getByRole('searchbox'), { key: 'Escape' });

    expect(screen.queryByRole('listbox')).toBeNull();
    expect(screen.getByRole('button', { name: /Customer/ }).getAttribute('aria-expanded')).toBe('false');
  });

  it('reads No results for an empty reply', async () => {
    server(reply({ items: [], has_more: false }));
    renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();

    expect(screen.getByText('No results')).toBeTruthy();
  });

  it('loads the next page with More', async () => {
    const { urls } = server(reply({ items: [anns[0]], has_more: true }), reply({ items: [anns[1]], has_more: false }));
    renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.click(screen.getByRole('button', { name: 'More' }));
    await flush();

    expect(urls()[1]).toBe('/lathe/api/resources/orders/associatable/customer?q=&page=2');
    expect(screen.getAllByRole('option')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'More' })).toBeNull();
  });

  it('toasts with Retry when the lookup fails, and Retry asks again', async () => {
    const { urls } = server(reply({ message: 'boom' }, 500));
    renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();

    const toast = __panel.toasts.get()[0];
    expect(toast?.action?.label).toBe('Retry');

    act(() => {
      toast?.action?.onClick();
    });
    await flush();

    expect(urls()).toHaveLength(2);
    expect(screen.getAllByRole('option')).toHaveLength(2);
  });

  it('shows With trashed only when the field allows it, and adds with_trashed=1', async () => {
    server();
    const { unmount } = renderField();

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    expect(screen.queryByLabelText('With trashed')).toBeNull();
    unmount();

    const { urls } = server();
    renderField(schema({ withTrashed: true }));
    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();
    fireEvent.click(screen.getByLabelText('With trashed'));
    await flush();

    expect(urls().at(-1)).toBe('/lathe/api/resources/orders/associatable/customer?q=&page=1&with_trashed=1');
  });

  it('renders a select for a field that is not searchable and loads it on focus', async () => {
    const { fetch, urls } = server(reply({ items: anns, has_more: false }));
    const onChange = vi.fn();
    renderField(schema({ searchable: false }), onChange);

    expect(fetch).not.toHaveBeenCalled();

    fireEvent.focus(screen.getByLabelText('Customer'));
    await flush();

    expect(urls()).toEqual(['/lathe/api/resources/orders/associatable/customer?q=&page=1']);
    fireEvent.change(screen.getByLabelText('Customer'), { target: { value: '3' } });
    expect(onChange).toHaveBeenCalledWith({ value: 3, display: 'Ann Lee' });
  });

  it('switches a select to the searchable picker when its first page has more', async () => {
    server(reply({ items: anns, has_more: true }));
    renderField(schema({ searchable: false }));

    expect(screen.getByLabelText('Customer').tagName).toBe('SELECT');

    fireEvent.focus(screen.getByLabelText('Customer'));
    await flush();

    expect(screen.queryByRole('combobox')).toBeNull();
    expect(screen.getByRole('button', { name: /Customer/ }).getAttribute('aria-haspopup')).toBe('listbox');
  });

  it('shows the display text of a readonly field and no picker', () => {
    const { fetch } = server();
    renderField(schema({ readonly: true }), () => undefined, { value: 3, display: 'Ann Lee', href: '/lathe/resources/customers/3' });

    expect(screen.getByText('Ann Lee')).toBeTruthy();
    expect(screen.queryByRole('button')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('asks the record route in update mode', async () => {
    __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'update', id: 12 });
    const { urls } = server();
    renderField(schema(), () => undefined, { value: 3, display: 'Ann Lee' });

    expect(screen.getByRole('button', { name: /Customer/ }).textContent).toContain('Ann Lee');

    fireEvent.click(screen.getByRole('button', { name: /Customer/ }));
    await flush();

    expect(urls()).toEqual(['/lathe/api/resources/orders/12/customer/associatable?q=&page=1']);
  });
});
