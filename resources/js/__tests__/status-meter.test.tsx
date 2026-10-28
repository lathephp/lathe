import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DetailField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

const status = {
  key: 'status',
  attribute: 'status',
  label: 'Status',
  component: 'select-field',
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
  options: [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'cancelled', label: 'Cancelled' },
  ],
  badges: { pending: 'warning', paid: 'info', shipped: 'success', cancelled: 'neutral' },
  meter: ['pending', 'paid', 'shipped'],
} as FieldSchema;

function meter(container: HTMLElement) {
  const root = container.querySelector<HTMLElement>('[data-lathe-meter]');

  return {
    ticks: container.querySelectorAll('[data-lathe-meter-tick]').length,
    on: container.querySelectorAll('[data-lathe-meter-tick][data-on]').length,
    tone: root?.dataset.latheMeterTone,
    text: root?.textContent,
  };
}

describe('status meter', () => {
  it('fills two of three ticks for paid, in the info tone, with its label', () => {
    const { container } = render(<IndexField field={status} cell={{ value: 'paid', display: 'paid' }} />);

    expect(meter(container)).toEqual({ ticks: 3, on: 2, tone: 'info', text: 'Paid' });
    expect(container.querySelector('[data-lathe-badge-tone]')).toBeNull();
    expect([...container.querySelectorAll('[data-lathe-meter-tick]')].map((tick) => tick.className.includes('opacity-[0.22]'))).toEqual([false, false, true]);
  });

  it('fills only the ticks, so the label and the ticks stay visible on the row', () => {
    const { container } = render(<IndexField field={status} cell={{ value: 'paid', display: 'paid' }} />);
    const root = container.querySelector<HTMLElement>('[data-lathe-meter]');

    expect(root?.className).toContain('text-info');
    expect(root?.className).not.toMatch(/\bbg-/);
    expect([...container.querySelectorAll<HTMLElement>('[data-lathe-meter-tick]')].every((tick) => tick.className.includes('bg-info'))).toBe(true);
  });

  it('fills none for a value outside the meter', () => {
    const { container } = render(<IndexField field={status} cell={{ value: 'cancelled', display: 'cancelled' }} />);

    expect(meter(container)).toEqual({ ticks: 3, on: 0, tone: 'neutral', text: 'Cancelled' });
  });

  it('fills every tick for shipped on the detail page', () => {
    const { container } = render(<DetailField field={{ ...status, value: 'shipped', display: 'shipped' }} />);

    expect(meter(container)).toMatchObject({ ticks: 3, on: 3, text: 'Shipped' });
  });

  it('draws neutral ticks when the field has a meter but no badges', () => {
    const { container } = render(<IndexField field={{ ...status, badges: null }} cell={{ value: 'paid', display: 'paid' }} />);

    expect(meter(container)).toEqual({ ticks: 3, on: 2, tone: 'neutral', text: 'Paid' });
  });

  it('keeps the badge when the meter is null', () => {
    const { container } = render(<IndexField field={{ ...status, meter: null }} cell={{ value: 'paid', display: 'paid' }} />);

    expect(container.querySelector('[data-lathe-meter]')).toBeNull();
    expect(container.querySelector('[data-lathe-badge-tone]')?.textContent).toBe('Paid');
  });

  it.each([
    ['with badges', status],
    ['without badges', { ...status, badges: null }],
  ])('draws the meter inside an inline status cell %s', (_, field) => {
    renderPage(
      'Resources/Index',
      {
        resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false } },
        table: {
          columns: [{ ...field, sortKey: 'status', inline: true }],
          rows: [{ id: 12, title: 'ORD-00012', cells: { status: { value: 'paid', display: 'paid' } }, abilities: { view: true, update: true }, updatedAt: '2026-10-14T09:00:00.000Z' }],
          rowKey: 'id',
          sortKeys: [],
        },
        state: { page: 1, perPage: 25, hasMorePages: false },
        count: { value: 1, exact: true, capped: false, estimated: false },
      },
      { url: '/lathe/resources/orders' },
    );

    expect(meter(screen.getByRole('combobox', { name: 'Status' }))).toMatchObject({ ticks: 3, on: 2, text: 'Paid' });
  });
});
