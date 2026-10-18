import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

const columns = [
  { key: 'number', label: 'Number', component: 'text' },
  { key: 'status', label: 'Status', component: 'text' },
  { key: 'total_cents', label: 'Total', component: 'text' },
  { key: 'items', label: 'Quantity', component: 'text' },
];

const rows = Array.from({ length: 25 }, (_, index) => ({
  id: index + 1,
  title: `ORD-${String(index + 1)}`,
  cells: {
    number: { value: `ORD-${String(index + 1)}`, display: `ORD-${String(index + 1)}` },
    status: { value: 'paid', display: 'paid' },
    total_cents: { value: '10.00', display: '10.00' },
    items: { value: 2, display: '2' },
  },
  // No ability, so the rows have no menu column and the test reads the data columns only.
  abilities: {},
}));

function renderSummaries(summaries: unknown, hasSummaries = false) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25],
        hasSummaries,
        abilities: { create: false },
        definition: {
          debounce: 0.5,
          paginationType: 'links',
          clickAction: 'detail',
          polling: null,
          showPollingToggle: false,
          tableStyle: 'default',
          showColumnBorders: false,
          softDeletes: false,
        },
      },
      table: { columns, rows, rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: true },
      count: { value: 10000, exact: false, capped: false, estimated: false },
      summaries,
    },
    { url: '/lathe/resources/orders' },
  );
}

function footerRows(): string[][] {
  const footer = screen.getAllByRole('rowgroup')[2] as HTMLElement;

  return within(footer)
    .getAllByRole('row')
    .map((row) => Array.from(row.children, (cell) => cell.textContent));
}

describe('summary rows', () => {
  it('puts each total under its column with its label', () => {
    renderSummaries({
      allRecords: {
        total_cents: [{ label: 'Revenue', value: '1234.50' }],
        items: [
          { label: 'Sum', value: '9' },
          { label: null, value: '4' },
        ],
      },
      page: { total_cents: [{ label: 'Revenue', value: '250.00' }] },
      message: null,
    });

    expect(footerRows()).toEqual([
      // The first cell sits under the row checkboxes.
      ['', 'This page', '', 'Revenue250.00', ''],
      ['', 'All records', '', 'Revenue1234.50', 'Sum94'],
    ]);
  });

  it('shows the page totals and the message above the cap', () => {
    renderSummaries({
      allRecords: null,
      page: { total_cents: [{ label: 'Revenue', value: '250.00' }] },
      message: 'Narrow the filters to total all rows',
    });

    expect(footerRows()).toEqual([
      ['', 'This page', '', 'Revenue250.00', ''],
      ['', 'All records', 'Narrow the filters to total all rows'],
    ]);
  });

  it('draws no footer before the summaries arrive', () => {
    renderSummaries(undefined);

    expect(screen.getAllByRole('rowgroup')).toHaveLength(2);
  });

  it('holds a placeholder footer of the final height until the summaries arrive', () => {
    const { unmount } = renderSummaries(undefined, true);
    const footer = document.querySelector('tfoot') as HTMLElement;

    // The page has more rows, so the footer will have two rows.
    expect(footer.getAttribute('aria-hidden')).toBe('true');
    expect(footer.querySelectorAll('tr')).toHaveLength(2);
    expect(footer.querySelector('tr')?.className).toContain('h-10');
    unmount();

    renderSummaries({ allRecords: { total_cents: [{ label: 'Revenue', value: '1234.50' }] }, page: { total_cents: [{ label: 'Revenue', value: '250.00' }] }, message: null }, true);

    expect(document.querySelector('tfoot[aria-hidden]')).toBeNull();
    expect(footerRows()).toHaveLength(2);
    expect(footerRows()[1]).toEqual(['', 'All records', '', 'Revenue1234.50', '']);
  });

  it('draws no placeholder when the Resource has no summaries', () => {
    renderSummaries(null, true);

    expect(screen.getAllByRole('rowgroup')).toHaveLength(2);
  });
});
