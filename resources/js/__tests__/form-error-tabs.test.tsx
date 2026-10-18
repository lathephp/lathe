import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { FormFieldData, LayoutBlock } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function field(key: string, label: string): FormFieldData {
  return { key, attribute: key, label, component: 'text-field', sortable: false, computed: false, nullable: false, stacked: false, fullWidth: false, columnSpan: null, indexWidth: null, borderless: false, compact: false, textAlign: 'left', placeholder: null, help: null, helpWidth: null, wrapping: false, section: null, autocomplete: null, meta: {}, value: '' };
}

function panel(attribute: string, fields: string[]) {
  return { type: 'panel', attribute, name: attribute, fields, toolbar: false, collapsible: false, collapsed: false, hideHeading: true, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false };
}

function tab(attribute: string, label: string, fields: string[]) {
  return { attribute, label, icon: null, badge: null, badgeType: null, columns: null, borderless: false, items: [panel(`${attribute}-fields`, fields)] };
}

const layout = [
  { type: 'tab-group', attribute: 'tabs', label: null, queryKey: 'tab', activeTab: 0, vertical: false, contained: false, columnSpan: null, tabs: [tab('billing', 'Billing', ['total_cents']), tab('notes', 'Notes', ['note'])] },
] as unknown as LayoutBlock[];

describe('a failed save across form tabs', () => {
  it('opens the first marked tab once, and fixing a field there keeps the tab open', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ message: 'Invalid.', errors: { total_cents: ['Bad.'], note: ['Bad.'] } }), { status: 422, headers: { 'Content-Type': 'application/json' } }),
    );
    renderPage('Resources/Form', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } },
      mode: 'create',
      id: null,
      title: 'Create Order',
      breadcrumbs: [],
      form: { fields: [field('total_cents', 'Total'), field('note', 'Note')], layout },
      submitLabel: 'Create Order',
    });
    fireEvent.click(screen.getByRole('tab', { name: 'Notes' }));
    fireEvent.click(screen.getByRole('button', { name: 'Create Order' }));
    await screen.findByRole('alert');

    expect(screen.getByRole('tab', { name: /Billing/ }).getAttribute('aria-selected')).toBe('true');

    fireEvent.change(screen.getByLabelText('Total'), { target: { value: '12' } });

    expect(screen.getByRole('tab', { name: /Billing/ }).getAttribute('aria-selected')).toBe('true');
  });
});
