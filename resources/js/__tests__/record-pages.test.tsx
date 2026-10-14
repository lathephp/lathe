import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField, registerField, registries } from '../fields/registry';
import type { FieldSchema, FormFieldData } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  registries.index.delete('probe-field');
  registries.detail.delete('probe-field');
  registries.form.delete('probe-field');
});

const resource = { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } };

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

const panel = (fields: string[]) => ({
  type: 'panel' as const,
  attribute: 'details',
  name: 'Details',
  fields,
  toolbar: true,
  collapsible: false,
  collapsed: false,
  hideHeading: false,
  headingInCard: false,
  help: null,
  limit: null,
  columns: null,
  columnSpan: null,
  borderless: false,
});

function formFields(): FormFieldData[] {
  return [
    { ...schema('name', { required: true }), value: '' },
    { ...schema('country'), value: 'US' },
  ];
}

function renderForm(mode: 'create' | 'update' = 'create') {
  return renderPage('Resources/Form', {
    resource,
    mode,
    id: mode === 'update' ? 7 : null,
    title: mode === 'update' ? 'Ann' : 'Create Customer',
    breadcrumbs: [{ label: 'Dashboard', href: '/lathe' }, { label: 'Customers', href: '/lathe/resources/customers' }, { label: 'Create', href: null }],
    form: { fields: formFields(), layout: [panel(['name', 'country'])], updatedAt: mode === 'update' ? '2026-10-14T09:00:00.000Z' : undefined },
    submitLabel: mode === 'update' ? 'Update Customer' : 'Create Customer',
  });
}

function inputValue(label: RegExp): string {
  return screen.getByLabelText<HTMLInputElement>(label).value;
}

function bodyOf(init: RequestInit | undefined): Record<string, unknown> {
  return JSON.parse(typeof init?.body === 'string' ? init.body : '{}') as Record<string, unknown>;
}

function respond(status: number, body: unknown) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } }));
}

describe('field registries', () => {
  it('draws a key with no entry through the fallbacks', () => {
    const { container } = renderPage('Resources/Detail', {
      resource,
      breadcrumbs: [],
      record: {
      id: 7,
      title: 'Ann',
      subtitle: '',
      avatar: null,
      fields: [{ ...schema('name', { component: 'unknown-field' }), value: 'Ann', display: 'Ann Lee' }],
      layout: [panel(['name'])],
      abilities: { view: true, update: true, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
      },
    });

    expect(within(container).getByText('Ann Lee')).toBeDefined();
  });

  it('uses a registered renderer in each context', () => {
    registerField('probe-field', {
      index: () => 'index probe',
      detail: () => 'detail probe',
      form: () => 'form probe',
    });
    const field = schema('probe', { component: 'probe-field' });

    render(
      <>
        <IndexField field={field} cell={{ value: 1, display: '1' }} />
        <DetailField field={{ ...field, value: 1, display: '1' }} />
        <FormField field={{ ...field, value: 1 }} value={1} onChange={() => undefined} id="x" describedBy={undefined} invalid={false} disabled={false} />
      </>,
    );

    expect(document.body.textContent).toBe('index probedetail probeform probe');
  });
});

describe('detail page', () => {
  it('shows the title, the breadcrumbs, the fields in their panel and Edit only when update is allowed', () => {
    const props = {
      resource: { ...resource, uriKey: 'orders', label: 'Orders', singularLabel: 'Order' },
      record: {
      id: 12,
      title: 'ORD-00012',
      subtitle: 'Paid',
      avatar: null,
      fields: [{ ...schema('status'), value: 'paid', display: 'paid' }],
      layout: [{ ...panel(['status']), name: 'Order', attribute: 'order' }],
      abilities: { view: true, update: true, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
      },
      breadcrumbs: [{ label: 'Dashboard', href: '/lathe' }, { label: 'Orders', href: '/lathe/resources/orders' }, { label: '#12', href: null }],
    };

    renderPage('Resources/Detail', props);

    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('ORD-00012');
    expect(screen.getByRole('navigation', { name: 'Breadcrumb' }).textContent).toBe('Dashboard/Orders/#12');
    expect(within(screen.getByRole('region', { name: 'Order' })).getByText('paid')).toBeDefined();
    expect(screen.getByRole('link', { name: 'Edit' }).getAttribute('href')).toBe('/lathe/resources/orders/12/edit');
  });

  it('hides Edit when update is denied', () => {
    renderPage('Resources/Detail', {
      resource,
      breadcrumbs: [],
      record: {
      id: 7,
      title: 'Ann',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [],
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
      },
    });

    expect(screen.queryByRole('link', { name: 'Edit' })).toBeNull();
  });
});

describe('record form', () => {
  it('creates and follows the redirect', async () => {
    const fetch = respond(201, { id: 9, redirect: 'customers/9' });
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderForm();

    fireEvent.change(screen.getByLabelText(/Name/), { target: { value: 'Ann Lee' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/9');
    });
    const [url, init] = fetch.mock.calls[0] ?? [];
    expect(url).toBe('/lathe/api/resources/customers');
    expect(bodyOf(init)).toEqual({ name: 'Ann Lee', country: 'US' });
  });

  it('returns to the parent on the relation tab after a create under a via', async () => {
    respond(201, { id: 50, redirect: 'order-items/50' });
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage(
      'Resources/Form',
      {
        resource: { uriKey: 'order-items', label: 'Order Items', singularLabel: 'Order Item', abilities: { create: true } },
        mode: 'create',
        id: null,
        title: 'Create Order Item',
        breadcrumbs: [],
        form: { fields: [{ ...schema('quantity'), value: '2' }], layout: [panel(['quantity'])] },
        submitLabel: 'Create Order Item',
      },
      { url: '/lathe/resources/order-items/new?via=orders%2F12%2Fitems' },
    );

    fireEvent.click(screen.getByRole('button', { name: 'Create Order Item' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/orders/12?tab=items');
    });
  });

  it('shows a 422 under its field, keeps the typed values, focuses the field and toasts', async () => {
    respond(422, { message: 'The name field is required.', errors: { name: ['The name field is required.'] } });
    renderForm();

    fireEvent.change(screen.getByLabelText(/Country/), { target: { value: 'BD' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    const name = screen.getByLabelText(/Name/);
    await waitFor(() => {
      expect(screen.getByText('The name field is required.')).toBeDefined();
    });
    expect(name.getAttribute('aria-invalid')).toBe('true');
    expect(document.activeElement).toBe(name);
    expect(inputValue(/Country/)).toBe('BD');
    expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Please fix the highlighted fields.');
  });

  it('server_error_shows_retry_toast', async () => {
    const fetch = respond(500, { message: 'Server Error' });
    renderForm();

    fireEvent.change(screen.getByLabelText(/Name/), { target: { value: 'Ann' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create Customer' }));

    await waitFor(() => {
      expect(__panel.toasts.get()[0]?.message).toBe('Something went wrong. Please try again.');
    });
    expect(inputValue(/Name/)).toBe('Ann');

    fetch.mockResolvedValue(new Response(JSON.stringify({ id: 9, redirect: 'customers/9' }), { status: 201 }));
    vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    act(() => {
      __panel.toasts.get()[0]?.action?.onClick();
    });

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledTimes(2);
    });
  });

  it('Create & Add Another stays, clears the form to its defaults and toasts', async () => {
    respond(201, { id: 9, redirect: 'customers/9' });
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderForm();

    fireEvent.change(screen.getByLabelText(/Name/), { target: { value: 'Ann' } });
    fireEvent.change(screen.getByLabelText(/Country/), { target: { value: 'BD' } });
    fireEvent.click(screen.getByRole('button', { name: 'Create & Add Another' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toContain('Customer created.');
    });
    expect(inputValue(/Name/)).toBe('');
    expect(inputValue(/Country/)).toBe('US');
    expect(visit).not.toHaveBeenCalled();
  });

  it('Update & Continue Editing stays with the fresh values and token', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(
        (typeof input === 'string' ? input : '').endsWith('/update-fields')
          ? new Response(JSON.stringify({ fields: [{ ...schema('name'), value: 'Fresh' }, { ...schema('country'), value: 'GB' }], layout: [panel(['name', 'country'])], updatedAt: '2026-10-14T09:00:02.000Z' }))
          : new Response(JSON.stringify({ id: 7, redirect: 'customers/7' })),
      ),
    );
    renderForm('update');

    fireEvent.click(screen.getByRole('button', { name: 'Update & Continue Editing' }));

    await waitFor(() => {
      expect(inputValue(/Name/)).toBe('Fresh');
    });
    const [url, init] = fetch.mock.calls[0] ?? [];
    expect(url).toBe('/lathe/api/resources/customers/7');
    expect(init?.method).toBe('PUT');
    expect(bodyOf(init)._updated_at).toBe('2026-10-14T09:00:00.000Z');
  });
});
