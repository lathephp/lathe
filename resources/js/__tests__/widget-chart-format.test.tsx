import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BarCard } from '../widgets/charts/BarCard';
import { PartitionCard } from '../widgets/charts/PartitionCard';
import { PieCard } from '../widgets/charts/PieCard';
import { RadialCard } from '../widgets/charts/RadialCard';
import type { WidgetDescriptor } from '../widgets/types';

afterEach(() => {
  vi.unstubAllGlobals();
});

function widgetOf(component: string, options: Record<string, unknown> = {}): WidgetDescriptor {
  return {
    name: 'Chart',
    uriKey: 'chart',
    component,
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
    ...options,
  };
}

// With reduced motion Recharts skips its animations, so a chart draws on the first render.
function reduceMotion() {
  vi.stubGlobal('matchMedia', (query: string) => ({ matches: query.includes('reduce'), addEventListener: () => undefined, removeEventListener: () => undefined }));
}

function hiddenRows(): (string | null)[][] {
  return [...screen.getByRole('figure').querySelectorAll('tbody tr')].map((row) => [...row.children].map((cell) => cell.textContent));
}

const segments = [
  { label: 'Paid', value: 20, percentage: 66.67, color: 'var(--chart-1)' },
  { label: 'Open', value: 1, percentage: 33.33, color: '#ef4444' },
];

describe('chart number display', () => {
  it('formats bar values with the prefix and the suffix of the result', () => {
    reduceMotion();
    render(
      <BarCard
        widget={widgetOf('bar-widget', { showLabels: true })}
        value={{ bars: [{ label: 'Paid', value: 1200, color: 'var(--chart-1)' }], prefix: '$', suffix: 'order', format: '.2' }}
      />,
    );

    expect(hiddenRows()).toEqual([['Paid', '$1,200.00 orders']]);
    expect([...document.querySelectorAll('.recharts-label-list text')].map((node) => node.textContent)).toEqual(['$1,200.00 orders']);
  });

  it('keeps the plain locale number for a result that sets no display option', () => {
    reduceMotion();
    render(<BarCard widget={widgetOf('bar-widget')} value={{ bars: [{ label: 'Paid', value: 12345, color: 'var(--chart-1)' }] }} />);

    expect(hiddenRows()).toEqual([['Paid', '12,345']]);
  });

  it('formats pie values and keeps a singular suffix when inflection is off', () => {
    reduceMotion();
    render(<PieCard widget={widgetOf('pie-widget')} value={{ value: segments, innerRadius: 0, suffix: 'order', suffixInflection: false }} />);

    expect(hiddenRows()).toEqual([
      ['Paid', '20 order', '66.67%'],
      ['Open', '1 order', '33.33%'],
    ]);
  });

  it('formats partition values in the legend and the hidden table', () => {
    reduceMotion();
    render(<PartitionCard widget={widgetOf('partition-widget')} value={{ value: segments, prefix: '€', format: 'a' }} />);

    expect(hiddenRows()).toEqual([
      ['Paid', '€20', '66.67%'],
      ['Open', '€1', '33.33%'],
    ]);
    expect(screen.getAllByText('€20').length).toBeGreaterThan(0);
  });

  it('formats the radial gauge value and target', () => {
    reduceMotion();
    render(
      <RadialCard
        widget={widgetOf('radial-widget')}
        value={{ value: 40, target: 80, percentage: 50, startAngle: 180, endAngle: 0, showTrack: true, showLabels: false, showGrid: false, color: 'var(--warning)', prefix: '$' }}
      />,
    );

    expect(hiddenRows()).toEqual([
      ['Value', '$40'],
      ['Target', '$80'],
    ]);
  });
});
