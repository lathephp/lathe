import { __panel } from '@lathephp/core';
import { act, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { choose, optionLabels, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

function field(key: string, label: string, value: unknown, extra: Record<string, unknown> = {}) {
  return { key, attribute: key, label, component: 'text-field', sortable: false, computed: false, nullable: false, stacked: false, fullWidth: false, columnSpan: null, indexWidth: null, borderless: false, compact: false, textAlign: 'left', placeholder: null, help: null, helpWidth: null, wrapping: false, section: null, autocomplete: null, meta: {}, value, ...extra };
}

const options = (...values: string[]) => values.map((value) => ({ value, label: value }));

function renderCustomers() {
  return renderPage('Resources/Form', {
    resource: { uriKey: 'customers', label: 'Customers', singularLabel: 'Customer', abilities: { create: true } },
    mode: 'create',
    id: null,
    title: 'Create Customer',
    breadcrumbs: [],
    form: {
      fields: [
        field('country', 'Country', 'US', { component: 'select-field', options: options('US', 'BD') }),
        field('state', 'State', '', { component: 'select-field', options: [], optionsFrom: { field: 'country', map: { US: options('NY', 'CA'), BD: options('Dhaka') } } }),
        field('type', 'Type', 'person'),
        field('company_name', 'Company name', '', { dependsOn: ['type'] }),
        field('code', 'Code', '', { dependsOn: ['type'] }),
      ],
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['country', 'state', 'type', 'company_name', 'code'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
    },
    submitLabel: 'Create Customer',
  });
}

const optionTexts = (label: RegExp) => optionLabels(screen.getByLabelText(label));

describe('dependent fields', () => {
  it('takes the State options from the Country value in the browser', async () => {
    vi.spyOn(globalThis, 'fetch');
    renderCustomers();

    expect(await optionTexts(/State/)).toContain('NY');
    await choose(screen.getByLabelText(/Country/), 'BD');
    const states = await optionTexts(/State/);
    expect(states).toContain('Dhaka');
    expect(states).not.toContain('NY');
  });

  it('asks the server 300 ms after the last change, applies its patches and never overwrites a typed value', async () => {
    vi.useFakeTimers();
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ patches: { company_name: { visible: true, required: true }, code: {} }, changed: { company_name: 'Acme Ltd' } }), { status: 200 }),
    );
    renderCustomers();

    fireEvent.change(screen.getByLabelText(/Code/), { target: { value: 'typed' } });
    fireEvent.change(screen.getByLabelText(/^Type/), { target: { value: 'compan' } });
    act(() => {
      vi.advanceTimersByTime(200);
    });
    fireEvent.change(screen.getByLabelText(/^Type/), { target: { value: 'company' } });
    act(() => {
      vi.advanceTimersByTime(299);
    });
    expect(fetch).not.toHaveBeenCalled();

    await act(async () => {
      vi.advanceTimersByTime(1);
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(fetch).toHaveBeenCalledTimes(1);
    const body = JSON.parse(typeof fetch.mock.calls[0]?.[1]?.body === 'string' ? fetch.mock.calls[0][1].body : '{}') as { fields: string[]; dirty: string[]; context: string; values: Record<string, unknown> };
    expect(body.fields).toEqual(['company_name', 'code']);
    expect(body.dirty).toEqual(['code', 'type']);
    expect(body.values.type).toBe('company');
    expect(screen.getByLabelText<HTMLInputElement>(/Company name/).value).toBe('Acme Ltd');
    expect(screen.getByLabelText<HTMLInputElement>(/Company name/).getAttribute('aria-required')).toBe('true');
    expect(screen.getByLabelText<HTMLInputElement>(/Code/).value).toBe('typed');
  });

  it('keeps every field as it was and toasts with Retry when the lookup fails', async () => {
    vi.useFakeTimers();
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ message: 'boom' }), { status: 500 }));
    renderCustomers();

    fireEvent.change(screen.getByLabelText(/^Type/), { target: { value: 'company' } });
    await act(async () => {
      vi.advanceTimersByTime(300);
      await Promise.resolve();
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(__panel.toasts.get()[0]?.action?.label).toBe('Retry');
    expect(screen.getByLabelText<HTMLInputElement>(/Company name/).value).toBe('');
  });
});
