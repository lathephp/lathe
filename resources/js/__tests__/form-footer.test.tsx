import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema, FormFieldData } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  window.sessionStorage.clear();
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
    { ...schema('name'), value: 'Ann' },
    { ...schema('country'), value: 'US' },
  ];
}

function renderForm(mode: 'create' | 'update' = 'create') {
  return renderPage('Resources/Form', {
    resource,
    mode,
    id: mode === 'update' ? 7 : null,
    title: mode === 'update' ? 'Ann' : 'Create Customer',
    breadcrumbs: [],
    form: { fields: formFields(), layout: [panel(['name', 'country'])], updatedAt: mode === 'update' ? '2026-10-14T09:00:00.000Z' : undefined },
    submitLabel: mode === 'update' ? 'Update Customer' : 'Create Customer',
  });
}

function json(body: unknown) {
  return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });
}

/** The bar that holds Cancel and the save buttons. */
function footer(): HTMLElement {
  const bar = screen.getByRole('link', { name: 'Cancel' }).closest<HTMLElement>('[data-lathe-form-footer]');

  if (bar === null) {
    throw new Error('Cancel is in no form footer.');
  }

  return bar;
}

describe('the form footer', () => {
  it('shows Unsaved changes beside Cancel and the save buttons once a field changes', () => {
    renderForm();

    expect(within(footer()).getByRole('button', { name: 'Create Customer' })).toBeTruthy();
    expect(within(footer()).queryByText('Unsaved changes')).toBeNull();

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Bea' } });

    expect(within(footer()).getByText('Unsaved changes')).toBeTruthy();
  });

  it('sticks the footer to the bottom of the viewport while the form scrolls', () => {
    renderForm();

    // jsdom lays nothing out, so the test pins the classes that make the bar sticky.
    expect(footer().className.split(' ')).toEqual(expect.arrayContaining(['sticky', 'bottom-0']));
  });

  it('drops Unsaved changes after Update & Continue Editing saves', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ id: 7, redirect: 'customers/7' }))
      .mockResolvedValueOnce(json({ fields: [{ ...schema('name'), value: 'Bea' }, { ...schema('country'), value: 'US' }], layout: [panel(['name', 'country'])], updatedAt: '2026-10-14T10:00:00.000Z' }));
    renderForm('update');

    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Bea' } });
    expect(within(footer()).getByText('Unsaved changes')).toBeTruthy();

    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Update & Continue Editing' }));
      await Promise.resolve();
    });

    await waitFor(() => {
      expect(within(footer()).queryByText('Unsaved changes')).toBeNull();
    });
    expect(screen.getByLabelText<HTMLInputElement>('Name').value).toBe('Bea');
  });
});

describe('the form layout', () => {
  it('centres the form at 880 px and gives the first field the full width', () => {
    renderForm();
    const name = screen.getByLabelText('Name');
    const column = name.closest('.max-w-\\[880px\\]');

    expect(column?.className).toContain('mx-auto');
    expect(screen.getByText('Name', { selector: 'label' }).parentElement?.className).toContain('lg:col-span-2');
    expect(screen.getByText('Country', { selector: 'label' }).parentElement?.className).not.toContain('lg:col-span-2');
    expect(screen.getByText('Name', { selector: 'label' }).parentElement?.parentElement?.className).toContain('lg:grid-cols-2');
  });
});
