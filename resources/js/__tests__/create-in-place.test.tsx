import { __panel } from '@lathephp/core';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerRelationFields } from '../fields/builtin/relation';
import { registerTextFields } from '../fields/builtin/text';
import { FormField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

registerRelationFields();
registerTextFields();

function schema(key: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key,
    attribute: key,
    label: key.charAt(0).toUpperCase() + key.slice(1),
    component: 'text-field',
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
    ...overrides,
  };
}

const customer = schema('customer', {
  component: 'belongs-to-field',
  kind: 'belongsTo',
  relationship: 'customer',
  resource: 'customers',
  searchable: true,
  debounce: 0,
  singularLabel: 'Customer',
  pluralLabel: 'Customers',
  creatable: true,
  createTitle: 'name',
});

const creation = {
  fields: [
    { ...schema('name', { required: true }), value: '' },
    { ...schema('email'), value: '' },
  ],
  defaults: {},
  borderless: false,
  layout: [
    { type: 'panel', attribute: 'details', name: 'Details', fields: ['name', 'email'], toolbar: true, collapsible: false, collapsed: false, hideHeading: true, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false },
  ],
};

function reply(body: unknown, status = 200) {
  return new Response(JSON.stringify(body), { status });
}

/** Answers by route: no customer matches a search, `create` answers the POST, `current=9` is the new record. */
function server(create: Response = reply({ id: 9, redirect: '/lathe/resources/customers/9' }, 201)) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;

    if (url.includes('/creation-fields')) {
      return Promise.resolve(reply(creation));
    }

    if (init?.method === 'POST') {
      return Promise.resolve(create);
    }

    if (url.includes('current=9')) {
      return Promise.resolve(reply({ items: [{ value: 9, display: 'Hanna Vos' }], has_more: false }));
    }

    return Promise.resolve(reply({ items: [], has_more: false }));
  });
}

function posts(fetch: ReturnType<typeof server>) {
  return fetch.mock.calls.filter(([, init]) => init?.method === 'POST');
}

/** The order form: a number input beside the customer field, which keeps what onChange gives it. */
function OrderForm({ onChange }: { onChange: (value: unknown) => void }) {
  const [value, setValue] = useState<unknown>(null);

  return (
    <form>
      <input aria-label="Number" defaultValue="ORD-1" />
      <label htmlFor="field-customer">Customer</label>
      <FormField
        field={{ ...customer, value: null }}
        value={value}
        onChange={(next) => {
          onChange(next);
          setValue(next);
        }}
        id="field-customer"
        describedBy={undefined}
        invalid={false}
        disabled={false}
      />
    </form>
  );
}

async function typeHannaVos() {
  fireEvent.click(await screen.findByRole('button', { name: 'Customer' }));
  fireEvent.change(screen.getByRole('searchbox', { name: 'Search' }), { target: { value: 'Hanna Vos' } });

  return screen.findByRole('option', { name: 'Create customer "Hanna Vos"' });
}

async function openSheet() {
  fireEvent.click(await typeHannaVos());

  const dialog = await screen.findByRole('dialog', { name: 'Create customer' });
  await within(dialog).findByLabelText(/Name/);

  return dialog;
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('creating a related record from the picker', () => {
  it('offers to create the typed name as the last result', async () => {
    server();
    render(<OrderForm onChange={() => undefined} />);

    const option = await typeHannaVos();

    expect(screen.getAllByRole('option').at(-1)).toBe(option);
  });

  it('opens a 480 px sheet with the typed name in the title field', async () => {
    server();
    render(<OrderForm onChange={() => undefined} />);

    const dialog = await openSheet();

    expect(within(dialog).getByLabelText<HTMLInputElement>(/Name/).value).toBe('Hanna Vos');
    expect(dialog.className).toContain('max-w-[480px]');
  });

  it('saves, closes the sheet and fills the field with the new record', async () => {
    server();
    const onChange = vi.fn();
    render(<OrderForm onChange={onChange} />);

    const dialog = await openSheet();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Create customer' }));

    await waitFor(() => {
      expect(screen.queryByRole('dialog')).toBeNull();
    });

    expect(onChange).toHaveBeenCalledWith({ value: 9, display: 'Hanna Vos' });
    expect(screen.getByRole('button', { name: 'Customer' }).textContent).toBe('Hanna Vos');
    expect(screen.getByLabelText<HTMLInputElement>('Number').value).toBe('ORD-1');
  });

  it('closes on Escape without creating anything', async () => {
    const fetch = server();
    render(<OrderForm onChange={() => undefined} />);

    await openSheet();
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(posts(fetch)).toEqual([]);
  });

  it('opens the picker with an empty search after the sheet is dismissed', async () => {
    server();
    render(<OrderForm onChange={() => undefined} />);

    await openSheet();
    fireEvent.keyDown(document, { key: 'Escape' });
    fireEvent.click(screen.getByRole('button', { name: 'Customer' }));

    expect(screen.getByRole('searchbox', { name: 'Search' })).toHaveProperty('value', '');
  });

  it('shows a 422 inside the sheet and leaves the order form alone', async () => {
    server(reply({ errors: { email: ['The email has already been taken.'] } }, 422));
    const onChange = vi.fn();
    render(<OrderForm onChange={onChange} />);

    const dialog = await openSheet();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Create customer' }));

    expect(await within(dialog).findByText('The email has already been taken.')).toBeDefined();
    expect(screen.getByRole('dialog', { name: 'Create customer' })).toBe(dialog);
    expect(onChange).not.toHaveBeenCalled();
    expect(screen.getByLabelText<HTMLInputElement>('Number').value).toBe('ORD-1');
  });
});
