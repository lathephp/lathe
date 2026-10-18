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
  Array.from({ length: count }, (_, index) => ({ label: `Category ${String(index + 1)}`, value: 100 - index, percentage: 5, color: `var(--chart-${String((index % 5) + 1)})` }));

describe('a pie with many slices', () => {
  it('draws 20 slices as the top five bars and the rest as Other', () => {
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
      'Other1,320',
    ]);
    expect(rows[5]?.querySelector('[data-bar]')).toBeNull();
    expect(rows[0]?.querySelector('[data-bar]')).not.toBeNull();
    expect(document.querySelector('svg.recharts-surface')).toBeNull();
  });

  it('keeps six slices as a pie', () => {
    render(<PieCard widget={widget} value={{ value: slices(6), innerRadius: 0 }} />);

    expect(screen.queryByRole('list', { name: 'Products by category' })).toBeNull();
  });
});
