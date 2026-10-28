import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DetailField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { cellOf, renderPage } from './support';

const options = [
  { value: 'pending', label: 'Pending' },
  { value: 'paid', label: 'Paid' },
  { value: 'shipped', label: 'Shipped' },
  { value: 'cancelled', label: 'Cancelled' },
];
const badges = { pending: 'warning', paid: 'success', shipped: 'info', cancelled: 'neutral' };

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
  options,
  badges,
} as FieldSchema;

function badge(container: HTMLElement) {
  return container.querySelector<HTMLElement>('[data-lathe-badge-tone]');
}

describe('select badges', () => {
  it('shows paid as a success badge reading Paid and cancelled as a neutral one', () => {
    const paid = render(<IndexField field={status} cell={{ value: 'paid', display: 'paid' }} />).container;
    const cancelled = render(<IndexField field={status} cell={{ value: 'cancelled', display: 'cancelled' }} />).container;

    expect([badge(paid)?.textContent, badge(paid)?.dataset.latheBadgeTone]).toEqual(['Paid', 'success']);
    expect([badge(cancelled)?.textContent, badge(cancelled)?.dataset.latheBadgeTone]).toEqual(['Cancelled', 'neutral']);
    expect(badge(paid)?.className).toContain('bg-success-subtle');
  });

  it('marks each status with a dot in its solid colour', () => {
    const dot = (value: string) => badge(render(<IndexField field={status} cell={{ value, display: value }} />).container)?.querySelector('[data-lathe-badge-dot]')?.className;

    expect(dot('pending')).toContain('bg-warning');
    expect(dot('cancelled')).toContain('bg-chart-other');
  });

  it('shows the badge on the detail page and plain text without badges()', () => {
    expect(badge(render(<DetailField field={{ ...status, value: 'shipped', display: 'shipped' }} />).container)?.textContent).toBe('Shipped');
    expect(render(<IndexField field={{ ...status, badges: null }} cell={{ value: 'paid', display: 'paid' }} />).container.textContent).toBe('paid');
  });

  it('draws the badge inside an inline status cell', () => {
    renderPage(
      'Resources/Index',
      {
        resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false } },
        table: {
          columns: [{ ...status, sortKey: 'status', inline: true }],
          rows: [{ id: 12, title: 'ORD-00012', cells: { status: { value: 'paid', display: 'paid' } }, abilities: { view: true, update: true }, updatedAt: '2026-10-14T09:00:00.000Z' }],
          rowKey: 'id',
          sortKeys: [],
        },
        state: { page: 1, perPage: 25, hasMorePages: false },
        count: { value: 1, exact: true, capped: false, estimated: false },
      },
      { url: '/lathe/resources/orders' },
    );

    // The cell shows its value as a read-only cell would until an edit opens the list.
    expect(badge(cellOf('Status'))?.textContent).toBe('Paid');
  });
});
