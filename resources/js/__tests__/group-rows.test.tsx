import { router } from '@inertiajs/react';
import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const columns = [
  { key: 'number', label: 'Number', component: 'text', sortable: true, sortKey: 'number' },
  { key: 'total_cents', label: 'Total', component: 'text', sortable: true, sortKey: 'total_cents' },
];

function row(id: number, status: string) {
  return {
    id,
    title: `ORD-${String(id)}`,
    cells: {
      number: { value: `ORD-${String(id)}`, display: `ORD-${String(id)}` },
      total_cents: { value: '10.00', display: '10.00' },
    },
    // No ability, so the rows have no menu column and the test reads the data columns only.
    abilities: {},
    group: { key: status, title: `Status: ${status}`, description: status === 'paid' ? 'Settled orders' : null },
  };
}

function renderGrouped(options: { hasMorePages?: boolean; url?: string } = {}) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25],
        searchable: false,
        abilities: { create: false },
        definition: { debounce: 0.5 },
      },
      table: { columns, sortKeys: ['number', 'total_cents'], rows: [row(1, 'paid'), row(2, 'paid'), row(3, 'pending'), row(4, 'pending')], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: options.hasMorePages ?? false, sort: '' },
      count: null,
      groups: {
        available: [
          { id: 'status', label: 'Status' },
          { id: 'placed', label: 'Placed' },
        ],
        active: { id: 'status', direction: 'desc', collapsible: true },
      },
      summaries: {
        allRecords: null,
        page: null,
        message: null,
        groups: {
          paid: { total_cents: [{ label: 'Revenue', value: '20.00' }] },
          pending: { total_cents: [{ label: 'Revenue', value: '35.00' }] },
        },
      },
    },
    { url: options.url ?? '/lathe/resources/orders?group=status:desc' },
  );
}

/** The body rows as the text of their cells; rows and subtotals start with the checkbox column. */
function bodyRows(): string[][] {
  const body = screen.getAllByRole('rowgroup')[1] as HTMLElement;

  return within(body)
    .getAllByRole('row')
    .map((tableRow) => Array.from(tableRow.children, (cell) => cell.textContent));
}

describe('group rows', () => {
  it('draws a header and a subtotal for each group, and a collapsed group hides both its rows and its subtotal', () => {
    renderGrouped();

    expect(bodyRows()).toEqual([
      ['Status: paidSettled orders'],
      ['', 'ORD-1', '10.00'],
      ['', 'ORD-2', '10.00'],
      ['', 'Subtotal', 'Revenue20.00'],
      ['Status: pending'],
      ['', 'ORD-3', '10.00'],
      ['', 'ORD-4', '10.00'],
      ['', 'Subtotal', 'Revenue35.00'],
    ]);

    const paid = screen.getByRole('button', { name: 'Status: paid' });

    expect(paid.getAttribute('aria-expanded')).toBe('true');

    fireEvent.click(paid);

    expect(paid.getAttribute('aria-expanded')).toBe('false');
    expect(bodyRows()).toEqual([
      ['Status: paidSettled orders'],
      ['Status: pending'],
      ['', 'ORD-3', '10.00'],
      ['', 'ORD-4', '10.00'],
      ['', 'Subtotal', 'Revenue35.00'],
    ]);
  });

  it('hides the subtotal of the group that continues past the page bottom', () => {
    renderGrouped({ hasMorePages: true });

    expect(bodyRows()).toEqual([
      ['Status: paidSettled orders'],
      ['', 'ORD-1', '10.00'],
      ['', 'ORD-2', '10.00'],
      ['', 'Subtotal', 'Revenue20.00'],
      ['Status: pending'],
      ['', 'ORD-3', '10.00'],
      ['', 'ORD-4', '10.00'],
    ]);
  });
});

describe('group menu', () => {
  it('visits group=status:desc for Status descending and group=_none for None', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderGrouped({ url: '/lathe/resources/orders?group=status:asc&page=2' });
    const menu = screen.getByRole('combobox', { name: 'Group' });

    expect(within(menu).getAllByRole('option').map((option) => option.textContent)).toEqual([
      'None',
      'Status ascending',
      'Status descending',
      'Placed ascending',
      'Placed descending',
    ]);

    fireEvent.change(menu, { target: { value: 'status:desc' } });
    fireEvent.change(menu, { target: { value: '_none' } });

    expect((visit.mock.calls[0]?.[1] as { data: Record<string, unknown> }).data).toEqual({ group: 'status:desc' });
    expect((visit.mock.calls[1]?.[1] as { data: Record<string, unknown> }).data).toEqual({ group: '_none' });
  });
});
