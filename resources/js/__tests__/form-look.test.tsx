import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import type { FieldSchema, FormFieldData } from '../fields/types';
import { renderPage } from './support';

const resource = { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } };

function field(key: string, overrides: Partial<FormFieldData> = {}): FormFieldData {
  const schema: FieldSchema = {
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
  };

  return { ...schema, value: '', ...overrides };
}

function renderForm() {
  return renderPage('Resources/Form', {
    resource,
    mode: 'create',
    id: null,
    title: 'Create customer',
    breadcrumbs: [],
    form: {
      fields: [field('name', { required: true }), field('notes')],
      layout: [
        {
          type: 'panel',
          attribute: 'customer',
          name: 'Customer',
          fields: ['name', 'notes'],
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
        },
      ],
    },
    submitLabel: 'Create customer',
  });
}

describe('the form look', () => {
  it('heads each section inside its card', () => {
    renderForm();
    const card = screen.getByLabelText('Name').closest<HTMLElement>('[id="panel-customer"]');

    expect(card).not.toBeNull();
    expect(within(card as HTMLElement).getByRole('heading', { level: 2 }).textContent).toBe('Customer');
  });

  it('marks the optional fields and leaves the required ones bare', () => {
    renderForm();

    expect(screen.getByText('Notes', { selector: 'label' }).nextElementSibling?.textContent).toBe('Optional');
    expect(screen.getByText('Name', { selector: 'label' }).textContent).toBe('Name');
    expect(screen.getByText('Name', { selector: 'label' }).nextElementSibling?.textContent).not.toBe('Optional');
  });

  it('draws the text inputs with the panel input class', () => {
    renderForm();

    expect(screen.getByLabelText('Name').className).toBe('lathe-input');
  });

  it('draws Cancel as ghost, the second save as outline and the main one as the primary button', () => {
    renderForm();

    expect(screen.getByRole('link', { name: 'Cancel' }).className).toBe('lathe-button lathe-button-ghost lathe-button-size-default');
    expect(screen.getByRole('button', { name: 'Create and add another' }).className).toBe('lathe-button lathe-button-outline lathe-button-size-default');
    expect(screen.getByRole('button', { name: 'Create customer' }).className).toBe('lathe-button lathe-button-default lathe-button-size-default');
  });
});
