import { __panel } from '@lathephp/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
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

function customerField(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return schema('customer', {
    component: 'belongs-to-field',
    kind: 'belongsTo',
    relationship: 'customer',
    resource: 'customers',
    searchable: true,
    singularLabel: 'Customer',
    pluralLabel: 'Customers',
    creatable: true,
    modalSize: 'lg',
    ...overrides,
  });
}

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

/** Answers by route; `create` is the answer to the POST. */
function server(create: Response = reply({ id: 9, redirect: '/lathe/resources/customers/9' }, 201)) {
  return vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;

    if (url.includes('/creation-fields')) {
      return Promise.resolve(reply(creation));
    }

    if (init?.method === 'POST') {
      return Promise.resolve(create);
    }

    return Promise.resolve(reply({ items: [{ value: 9, display: 'Zed Inline' }], has_more: false }));
  });
}

function calls(fetch: ReturnType<typeof server>) {
  return fetch.mock.calls.map(([input, init]) => `${init?.method ?? 'GET'} ${typeof input === 'string' ? input : ''}`);
}

function renderField(field: FieldSchema, onChange: (value: unknown) => void = () => undefined) {
  return render(
    <>
      <label htmlFor="field-customer">Customer</label>
      <FormField field={{ ...field, value: null }} value={null} onChange={onChange} id="field-customer" describedBy={undefined} invalid={false} disabled={false} />
    </>,
  );
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('inline create from a belongsTo field', () => {
  it('shows the plus only when the field is creatable', () => {
    const first = renderField(customerField({ creatable: false }));

    expect(screen.queryByRole('button', { name: 'Create Customer' })).toBeNull();
    first.unmount();

    renderField(customerField());

    expect(screen.getByRole('button', { name: 'Create Customer' })).toBeDefined();
  });

  it('opens the related create form in a 480 px sheet whatever the modal size, with one request', async () => {
    const fetch = server();
    renderField(customerField({ modalSize: 'lg' }));

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    const dialog = await screen.findByRole('dialog');
    await screen.findByLabelText(/Name/);

    expect(dialog.className).toContain('max-w-[480px]');
    expect(screen.getByLabelText('Email')).toBeDefined();
    expect(calls(fetch)).toEqual(['GET /lathe/api/resources/customers/creation-fields']);
  });

  it('posts the values, selects the new record and closes the sheet', async () => {
    const fetch = server();
    const onChange = vi.fn();
    renderField(customerField(), onChange);

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    fireEvent.change(await screen.findByLabelText(/Name/), { target: { value: 'Zed Inline' } });
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'zed.inline@example.com' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create' }));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith({ value: 9, display: 'Zed Inline' });
    });

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(calls(fetch)).toEqual([
      'GET /lathe/api/resources/customers/creation-fields',
      'POST /lathe/api/resources/customers',
      'GET /lathe/api/resources/orders/associatable/customer?current=9',
    ]);
    expect(JSON.parse(fetch.mock.calls[1]?.[1]?.body as string)).toMatchObject({ name: 'Zed Inline', email: 'zed.inline@example.com' });
  });

  it('shows a 422 under its field and keeps the sheet open', async () => {
    server(reply({ errors: { email: ['The email has already been taken.'] } }, 422));
    const onChange = vi.fn();
    renderField(customerField(), onChange);

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    fireEvent.change(await screen.findByLabelText(/Name/), { target: { value: 'Zed' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create' }));

    expect(await screen.findByText('The email has already been taken.')).toBeDefined();
    expect(screen.getByRole('dialog')).toBeDefined();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('closes on Escape and on Cancel without saving', async () => {
    const fetch = server();
    renderField(customerField());

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    await screen.findByLabelText(/Name/);
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));
    await screen.findByLabelText(/Name/);
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(calls(fetch).filter((line) => line.startsWith('POST'))).toEqual([]);
  });
});
