import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

function field(key: string, label: string, value: unknown, extra: Record<string, unknown> = {}) {
  return { key, attribute: key, label, component: 'text-field', sortable: false, computed: false, nullable: false, stacked: false, fullWidth: false, columnSpan: null, indexWidth: null, borderless: false, compact: false, textAlign: 'left', placeholder: null, help: null, helpWidth: null, wrapping: false, section: null, autocomplete: null, meta: {}, value, ...extra };
}

const company = { op: 'eq', field: 'type', value: 'company' };

describe('conditions on the form', () => {
  it('shows Company name only for a company, marks it required then, and locks Vat for a person', () => {
    renderPage('Resources/Form', {
      resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } },
      mode: 'create',
      id: null,
      title: 'Create Customer',
      breadcrumbs: [],
      form: {
        fields: [
          field('type', 'Type', 'person'),
          field('company_name', 'Company name', '', { visibleWhen: company, requiredWhen: company }),
          field('vat', 'Vat', '', { readonlyWhen: { op: 'eq', field: 'type', value: 'person' } }),
        ],
        layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['type', 'company_name', 'vat'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
      },
      submitLabel: 'Create Customer',
    });

    expect(screen.queryByLabelText(/Company name/)).toBeNull();
    expect(screen.getByLabelText<HTMLInputElement>(/Vat/).disabled).toBe(true);

    fireEvent.change(screen.getByLabelText(/Type/), { target: { value: 'company' } });

    expect(screen.getByLabelText<HTMLInputElement>(/Company name/).getAttribute('aria-required')).toBe('true');
    expect(screen.getByLabelText<HTMLInputElement>(/Vat/).disabled).toBe(false);
  });
});
