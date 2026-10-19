import { fireEvent, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

function field(key: string, label: string, value: unknown, extra: Record<string, unknown> = {}) {
  return { key, attribute: key, label, component: 'text-field', sortable: false, computed: false, nullable: false, stacked: false, fullWidth: false, columnSpan: null, indexWidth: null, borderless: false, compact: false, textAlign: 'left', placeholder: null, help: null, helpWidth: null, wrapping: false, section: null, autocomplete: null, meta: {}, value, ...extra };
}

const company = { op: 'eq', field: 'type', value: 'company' };

function renderCustomerForm() {
  renderPage('Resources/Form', {
    resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } },
    mode: 'create',
    id: null,
    title: 'Create Customer',
    breadcrumbs: [],
    form: {
      fields: [
        field('name', 'Name', ''),
        field('type', 'Type', 'person', { options: [{ value: 'person', label: 'Person' }, { value: 'company', label: 'Company' }] }),
        field('company_name', 'Company name', '', { visibleWhen: company }),
        field('email', 'Email', ''),
      ],
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['name', 'type', 'company_name', 'email'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
    },
    submitLabel: 'Create Customer',
  });
}

describe('a dependent field on the form', () => {
  it('shows under its parent on a muted ground with the reason it appeared, and hides again', async () => {
    renderCustomerForm();
    const type = screen.getByLabelText('Type');

    fireEvent.change(type, { target: { value: 'company' } });

    const input = await screen.findByLabelText('Company name');
    const row = input.closest('[data-dependent]') as HTMLElement;
    const typeRow = type.closest('.flex.flex-wrap') as HTMLElement;
    expect(row.className).toContain('bg-muted');
    expect(row.className).toContain('lg:col-span-2');
    expect(row.textContent).toContain('Shown because Type is Company');
    expect(typeRow.nextElementSibling).toBe(row);

    fireEvent.change(type, { target: { value: 'person' } });

    expect(screen.queryByLabelText('Company name')).toBeNull();
  });
});
