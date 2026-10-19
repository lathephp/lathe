import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema, FormFieldData } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const resource = { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } };

function field(key: string, label: string, overrides: Partial<FieldSchema> = {}): FormFieldData {
  return {
    key,
    attribute: key,
    label,
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
    value: '',
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

function renderForm(fields: FormFieldData[]) {
  return renderPage('Resources/Form', {
    resource,
    mode: 'create',
    id: null,
    title: 'Create Order',
    breadcrumbs: [],
    form: { fields, layout: [panel(fields.map((item) => item.key))] },
    submitLabel: 'Create Order',
  });
}

function fail(errors: Record<string, string[]>) {
  vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ message: 'Invalid.', errors }), { status: 422, headers: { 'Content-Type': 'application/json' } }));
}

const fields = () => [field('reference', 'Reference'), field('total_cents', 'Total')];

async function save() {
  fireEvent.click(screen.getByRole('button', { name: 'Create Order' }));

  return screen.findByRole('alert');
}

describe('form error summary', () => {
  it('links each errored field by its label and focuses the control on click', async () => {
    fail({ total_cents: ['The total must be a number.'] });
    renderForm(fields());

    const alert = await save();
    const link = within(alert).getByRole('link', { name: 'Total' });
    fireEvent.click(link);

    expect(document.activeElement).toBe(document.getElementById('field-total_cents'));
    expect(alert.textContent).toContain('The total must be a number.');
  });

  it('colours the link as an error, not as a link', async () => {
    fail({ total_cents: ['Bad.'] });
    renderForm(fields());

    const link = within(await save()).getByRole('link', { name: 'Total' });

    expect(link.className).toContain('text-destructive-subtle-foreground');
    expect(link.className).not.toContain('text-primary');
  });

  it('clears a field error and its summary line as soon as the field changes', async () => {
    fail({ total_cents: ['Bad.'] });
    renderForm(fields());
    await save();

    fireEvent.change(screen.getByLabelText(/Total/), { target: { value: '12' } });

    expect(screen.queryByText('Bad.')).toBeNull();
    expect(screen.queryByRole('alert')).toBeNull();
  });

  it('keeps the other lines when one field is fixed', async () => {
    fail({ reference: ['Ref bad.'], total_cents: ['Bad.'] });
    renderForm(fields());
    const alert = await save();

    fireEvent.change(screen.getByLabelText(/Total/), { target: { value: '12' } });

    expect(within(alert).queryByRole('link', { name: 'Total' })).toBeNull();
    expect(within(alert).getByRole('link', { name: 'Reference' })).toBeDefined();
    expect(alert.textContent).toContain('1 field needs a change before you can save');
  });

  it('scrolls the summary into view and focuses the first errored field', async () => {
    const scroll = vi.fn();
    Element.prototype.scrollIntoView = scroll;
    fail({ reference: ['Ref bad.'], total_cents: ['Bad.'] });
    renderForm(fields());

    const alert = await save();

    await waitFor(() => {
      expect(document.activeElement).toBe(document.getElementById('field-reference'));
    });
    expect(scroll.mock.contexts).toContain(alert);
  });

  it('counts the fields that need a change', async () => {
    fail({ reference: ['Ref bad.'], total_cents: ['Bad.'] });
    const first = renderForm(fields());
    expect((await save()).textContent).toContain('2 fields need a change before you can save');
    first.unmount();

    fail({ total_cents: ['Bad.'] });
    renderForm(fields());
    expect((await save()).textContent).toContain('1 field needs a change before you can save');
  });
});

describe('readonly and disabled looks', () => {
  it('draws a readonly field on a muted ground with a dashed border, not dimmed', () => {
    const { container } = renderForm([field('reference', 'Reference', { readonly: true }), field('total_cents', 'Total')]);

    const row = container.querySelector('[data-readonly]');
    expect(row).not.toBeNull();
    expect(row?.querySelector('#field-reference')).not.toBeNull();
    expect(row?.className).toContain('[&_input]:border-dashed');
    expect(row?.className).toContain('[&_input]:bg-muted');
    expect(row?.className).not.toContain('opacity-50');
    expect(screen.getByLabelText<HTMLInputElement>(/Reference/).disabled).toBe(true);
  });

  it('dims a disabled control outside a readonly row', () => {
    const { container } = renderForm([field('reference', 'Reference', { readonly: true }), field('total_cents', 'Total')]);

    const row = container.querySelector('#field-total_cents')?.closest('div.flex-wrap');
    expect(row?.hasAttribute('data-readonly')).toBe(false);
    expect(row?.className).toContain('[&_:disabled]:opacity-50');
  });
});
