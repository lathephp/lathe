import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerRelationFields } from '../fields/builtin/relation';
import { FormField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { choose } from './support';

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

// The open list hides the page from the accessibility tree, the label too, so the input is found before it opens.
// Base UI opens the list on the press and mounts it a frame later, so this waits on the real clock for the first page.
async function openPicker() {
  const input = screen.getByRole<HTMLInputElement>('combobox', { name: 'Customer' });
  fireEvent.mouseDown(input);
  await waitFor(() => {
    expect(document.querySelector('[data-slot="combobox-list"]')).not.toBeNull();
  });
  await flush();

  return input;
}

// Typing waits on the fake clock; the list opened on the real one first.
function fakeClock() {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
}

describe('the Customer picker on an order edit form', () => {
  const annLee = { value: 3, display: 'Ann Lee' };

  beforeEach(() => {
    __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'update', id: 12 });
  });

  it('is a kit combobox listing only what the server returns for the term, an email-only match too', async () => {
    const byEmail = { value: 5, display: 'Bob Stone', subtitle: 'bob@anchor.test' };
    const { urls } = server(reply({ items: [...anns, { value: 6, display: 'Carl Moss' }], has_more: false }), reply({ items: [anns[0], byEmail], has_more: false }));
    renderField(schema(), () => undefined, annLee);

    const input = await openPicker();
    expect(input.tagName).toBe('INPUT');
    expect(input.value).toBe('Ann Lee');
    expect(document.querySelector('[data-slot="combobox-list"]')).not.toBeNull();

    fakeClock();
    fireEvent.change(input, { target: { value: 'an' } });
    await advance(500);

    expect(input.value).toBe('an');
    expect(urls()).toEqual(['/lathe/api/resources/orders/12/customer/associatable?q=&page=1', '/lathe/api/resources/orders/12/customer/associatable?q=an&page=1']);
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ann Leeann@example.com', 'Bob Stonebob@anchor.test']);
  });

  it('picks the first match with ArrowDown and Enter', async () => {
    server(reply({ items: anns, has_more: false }), reply({ items: anns, has_more: false }));
    const onChange = vi.fn();
    renderField(schema(), onChange, { value: 4, display: 'Anna Park' });

    const input = await openPicker();
    fakeClock();
    fireEvent.change(input, { target: { value: 'an' } });
    await advance(500);
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });

    expect(onChange).toHaveBeenCalledWith({ value: 3, display: 'Ann Lee' });
  });

  it('picks the first match with ArrowDown and Enter before any typing, past the chosen customer', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange, { value: 4, display: 'Anna Park' });

    const input = await openPicker();
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });

    expect(onChange).toHaveBeenCalledWith({ value: 3, display: 'Ann Lee' });
  });

  it('keeps the chosen customer while the search text is emptied', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange, annLee);

    const input = await openPicker();
    fireEvent.change(input, { target: { value: '' } });
    await flush();

    expect(onChange).not.toHaveBeenCalled();
  });

  it('closes on Escape and keeps the old value', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange, annLee);

    const input = await openPicker();
    fireEvent.keyDown(input, { key: 'Escape' });
    await flush();

    expect(screen.queryByRole('listbox')).toBeNull();

    // A second Escape, on the shut list, keeps it too, and reaches the page, where a dialog or an inline cell closes on it.
    const reached = vi.fn();
    document.addEventListener('keydown', reached);
    fireEvent.keyDown(input, { key: 'Escape' });
    document.removeEventListener('keydown', reached);
    await flush();

    expect(onChange).not.toHaveBeenCalled();
    expect(input.value).toBe('Ann Lee');
    expect(reached).toHaveBeenCalledTimes(1);
  });

  it('keeps the chosen value on Tab out', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange, annLee);

    const input = await openPicker();
    act(() => {
      input.focus();
    });
    fireEvent.change(input, { target: { value: 'zz' } });
    // Tab moves the focus to the next control; jsdom leaves that move to the test.
    const next = document.body.appendChild(document.createElement('button'));
    fireEvent.keyDown(input, { key: 'Tab' });
    act(() => {
      next.focus();
    });
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });

    expect(onChange).not.toHaveBeenCalled();
    expect(input.value).toBe('Ann Lee');
    next.remove();
  });

  it('draws With trashed as the kit checkbox, and toggling it reloads with trashed records', async () => {
    const { urls } = server(reply({ items: [anns[0]], has_more: false }), reply({ items: anns, has_more: false }));
    renderField(schema({ withTrashed: true }), () => undefined, annLee);

    await openPicker();
    const box = screen.getByRole('checkbox', { name: 'With trashed' });

    expect(box.getAttribute('data-slot')).toBe('checkbox');
    expect(box.classList.contains('cn-checkbox')).toBe(true);

    fireEvent.click(box);
    await flush();

    expect(urls().at(-1)).toBe('/lathe/api/resources/orders/12/customer/associatable?q=&page=1&with_trashed=1');
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ann Leeann@example.com', 'Anna Park']);
  });

  it('draws the select With trashed as the kit checkbox, and toggling it reloads with trashed records', async () => {
    const { urls } = server();
    renderField(schema({ searchable: false, withTrashed: true }), () => undefined, annLee);

    fireEvent.focus(screen.getByLabelText('Customer'));
    await flush();
    const box = screen.getByRole('checkbox', { name: 'With trashed' });

    expect(box.getAttribute('data-slot')).toBe('checkbox');
    expect(box.classList.contains('cn-checkbox')).toBe(true);

    fireEvent.click(box);
    await flush();

    expect(urls()).toEqual(['/lathe/api/resources/orders/12/customer/associatable?q=&page=1', '/lathe/api/resources/orders/12/customer/associatable?q=&page=1&with_trashed=1']);
  });
});

describe('belongsTo relation picker', () => {
  it('sends nothing on render and one request when the picker opens', async () => {
    const { fetch, urls } = server();
    renderField();

    expect(fetch).not.toHaveBeenCalled();

    await openPicker();

    expect(urls()).toEqual(['/lathe/api/resources/orders/associatable/customer?q=&page=1']);
    expect(screen.getAllByRole('option').map((option) => option.textContent)).toEqual(['Ann Leeann@example.com', 'Anna Park']);
  });

  it('waits 500 ms after typing before the next request', async () => {
    const { urls } = server();
    renderField();

    const input = await openPicker();
    fakeClock();
    fireEvent.change(input, { target: { value: 'ann' } });
    await advance(499);

    expect(urls()).toHaveLength(1);

    await advance(1);

    expect(urls()).toEqual(['/lathe/api/resources/orders/associatable/customer?q=&page=1', '/lathe/api/resources/orders/associatable/customer?q=ann&page=1']);
  });

  it('waits the field debounce of 250 ms instead', async () => {
    const { urls } = server();
    renderField(schema({ debounce: 250 }));

    const input = await openPicker();
    fakeClock();
    fireEvent.change(input, { target: { value: 'ann' } });
    await advance(249);

    expect(urls()).toHaveLength(1);

    await advance(1);

    expect(urls()[1]).toBe('/lathe/api/resources/orders/associatable/customer?q=ann&page=1');
  });

  it('calls onChange with the chosen value and display', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    await openPicker();
    fireEvent.click(screen.getByRole('option', { name: /Anna Park/ }));

    expect(onChange).toHaveBeenCalledWith({ value: 4, display: 'Anna Park' });
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
  });

  it('picks the highlighted option with the arrow keys and Enter', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    const input = await openPicker();
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter' });

    expect(onChange).toHaveBeenCalledWith({ value: 4, display: 'Anna Park' });
  });

  it('picks nothing on an Enter that confirms an IME composition', async () => {
    server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    const input = await openPicker();
    fireEvent.keyDown(input, { key: 'ArrowDown' });
    fireEvent.keyDown(input, { key: 'Enter', isComposing: true });

    expect(onChange).not.toHaveBeenCalled();
    expect(screen.getByRole('listbox')).toBeTruthy();
  });

  it('closes on Escape', async () => {
    server();
    renderField();

    const input = await openPicker();
    fireEvent.keyDown(input, { key: 'Escape' });

    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
    expect(input.getAttribute('aria-expanded')).toBe('false');
  });

  it('reads No results for an empty reply', async () => {
    server(reply({ items: [], has_more: false }));
    renderField();

    await openPicker();

    expect(screen.getByText('No results')).toBeTruthy();
  });

  it('loads the next page with More', async () => {
    const { urls } = server(reply({ items: [anns[0]], has_more: true }), reply({ items: [anns[1]], has_more: false }));
    renderField();

    await openPicker();
    fireEvent.click(screen.getByRole('button', { name: 'More' }));
    await flush();

    expect(urls()[1]).toBe('/lathe/api/resources/orders/associatable/customer?q=&page=2');
    expect(screen.getAllByRole('option')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'More' })).toBeNull();
  });

  it('toasts with Retry when the lookup fails, and Retry asks again', async () => {
    const { urls } = server(reply({ message: 'boom' }, 500));
    renderField();

    await openPicker();

    const toast = __panel.toasts.get()[0];
    expect(toast?.action?.label).toBe('Retry');

    act(() => {
      toast?.action?.onClick();
    });
    await flush();

    expect(urls()).toHaveLength(2);
    expect(screen.getAllByRole('option')).toHaveLength(2);
  });

  it('stops the search input at 100 characters', () => {
    server();
    renderField();

    expect(screen.getByRole('combobox', { name: 'Customer' }).getAttribute('maxlength')).toBe('100');
  });

  it('shows a refused term in the picker, with no toast and no earlier options', async () => {
    const toasts = __panel.toasts.get().length;
    server(reply({ items: anns, has_more: false }), reply({ message: 'The search term may not be longer than 100 characters.', errors: { q: ['The search term may not be longer than 100 characters.'] } }, 422));
    renderField();

    const input = await openPicker();
    fakeClock();
    fireEvent.change(input, { target: { value: 'x'.repeat(101) } });
    await advance(500);

    expect(screen.queryAllByRole('option')).toEqual([]);
    expect(screen.getByRole('alert').textContent).toBe('The search term may not be longer than 100 characters.');
    expect(__panel.toasts.get()).toHaveLength(toasts);
  });

  it('says plainly when the user may not list the records, with no toast', async () => {
    const toasts = __panel.toasts.get().length;
    server(reply({ message: 'This action is unauthorized.' }, 403));
    renderField();

    await openPicker();

    expect(screen.queryAllByRole('option')).toEqual([]);
    expect(screen.getByRole('alert').textContent).toBe('You may not list these records.');
    expect(__panel.toasts.get()).toHaveLength(toasts);
  });

  it('shows With trashed only when the field allows it, and adds with_trashed=1', async () => {
    server();
    const { unmount } = renderField();

    await openPicker();
    expect(screen.queryByRole('checkbox', { name: 'With trashed' })).toBeNull();
    unmount();

    const { urls } = server();
    renderField(schema({ withTrashed: true }));
    await openPicker();
    fireEvent.click(screen.getByRole('checkbox', { name: 'With trashed' }));
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
    await choose(screen.getByLabelText('Customer'), 'Ann Lee');
    expect(onChange).toHaveBeenCalledWith({ value: 3, display: 'Ann Lee' });
  });

  it('switches a select to the searchable picker when its first page has more', async () => {
    server(reply({ items: anns, has_more: true }));
    renderField(schema({ searchable: false }));

    expect(screen.getByLabelText('Customer').getAttribute('role')).toBe('combobox');
    expect(document.querySelector('select')).toBeNull();

    fireEvent.focus(screen.getByLabelText('Customer'));
    await flush();

    // The picker's search input replaces the select's trigger button.
    expect(screen.getByRole('combobox', { name: 'Customer' }).tagName).toBe('INPUT');
  });

  it('shows the display text of a readonly field and no picker', () => {
    const { fetch } = server();
    renderField(schema({ readonly: true }), () => undefined, { value: 3, display: 'Ann Lee', href: '/lathe/resources/customers/3' });

    expect(screen.getByText('Ann Lee')).toBeTruthy();
    expect(screen.queryByRole('combobox')).toBeNull();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('asks the record route in update mode', async () => {
    __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'update', id: 12 });
    const { urls } = server();
    renderField(schema(), () => undefined, { value: 3, display: 'Ann Lee' });

    expect(screen.getByRole<HTMLInputElement>('combobox', { name: 'Customer' }).value).toBe('Ann Lee');

    await openPicker();

    expect(urls()).toEqual(['/lathe/api/resources/orders/12/customer/associatable?q=&page=1']);
  });
});
