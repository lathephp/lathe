import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PieCard, type Segment } from '../widgets/charts/PieCard';
import type { WidgetDescriptor } from '../widgets/types';

const widget: WidgetDescriptor = {
  name: 'Products by category',
  uriKey: 'products-by-category',
  component: 'pie-widget',
  width: '1/3',
  columns: 4,
  height: 'fixed',
  help: null,
  helpWidth: 250,
  refreshEvery: 0,
  refreshWhenActionsRun: false,
  refreshWhenFiltersChange: false,
  onlyOnDetail: false,
  requestsValue: true,
  ranges: [],
};

const slices = (count: number): Segment[] =>
  Array.from({ length: count }, (_, index) => ({ label: `Category ${String(index + 1)}`, value: 100 - index, percentage: 5, color: index === 0 ? '#ef4444' : `var(--chart-${String((index % 5) + 1)})` }));

describe('a pie with many slices', () => {
  it('draws 20 slices as the top five bars in the chart colours and the rest as one row', () => {
    render(<PieCard widget={widget} value={{ value: slices(20), innerRadius: 0 }} />);

    const list = screen.getByRole('list', { name: 'Products by category' });
    const rows = within(list).getAllByRole('listitem');

    expect(rows.map((row) => row.textContent)).toEqual([
      'Category 1100',
      'Category 299',
      'Category 398',
      'Category 497',
      'Category 596',
      // 95 down to 81, the fifteen slices past the fifth.
      '15 more1,320',
    ]);
    expect(rows[5]?.querySelector('[data-bar]')).toBeNull();
    expect(rows.slice(0, 5).map((row) => row.querySelector<HTMLElement>('[data-bar]')?.style.backgroundColor)).toEqual([
      'var(--chart-1)',
      'var(--chart-2)',
      'var(--chart-3)',
      'var(--chart-4)',
      'var(--chart-5)',
    ]);
    expect(rows[0]?.querySelector('[data-bar]')).not.toBeNull();
    expect(document.querySelector('svg.recharts-surface')).toBeNull();
  });

  it('keeps six slices as a pie', () => {
    render(<PieCard widget={widget} value={{ value: slices(6), innerRadius: 0 }} />);

    expect(screen.queryByRole('list', { name: 'Products by category' })).toBeNull();
  });
});

describe('a long slice label among many', () => {
  it('wraps whole beside its bar, with the bar and the value on its first line', () => {
    const label = 'A category named at forty characters long';
    render(<PieCard widget={widget} value={{ value: [{ ...slices(1)[0], label }, ...slices(20).slice(1)] }} />);
    const bars = within(screen.getByRole('list', { name: 'Products by category' }));
    const name = bars.getByText(label);

    expect(label).toHaveLength(41);
    expect(name.className.split(' ')).not.toContain('truncate');
    expect(name.className.split(' ')).toContain('wrap-break-word');
    expect((name.parentElement as HTMLElement).className.split(' ')).toContain('items-start');
    expect(bars.getByText('15 more').className.split(' ')).not.toContain('truncate');
  });
});
