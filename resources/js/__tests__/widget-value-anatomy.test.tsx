import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { cellClass } from '../widgets/layout';
import type { WidgetDescriptor } from '../widgets/types';
import { ValueCard } from '../widgets/ValueCard';

const widget: WidgetDescriptor = {
  name: 'Orders',
  uriKey: 'orders',
  component: 'value-widget',
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

function showValue(overrides: Record<string, unknown>) {
  const result = { value: 0, previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false, lowerIsBetter: false };

  return render(<ValueCard widget={widget} value={{ ...result, ...overrides }} />);
}

describe('a value card', () => {
  it('shows its value in full digits, the change badge and "vs" the previous value', () => {
    showValue({ value: 1948466.99, previous: 1852565.92, change: 5.18, prefix: '$' });

    expect(screen.getByText('$1,948,466.99')).toBeTruthy();
    expect(screen.getByText('+5.18%').closest('[data-change]')?.getAttribute('data-change')).toBe('up');
    expect(screen.getByText('vs $1,852,565.92')).toBeTruthy();
  });

  it('keeps a short form for a phone beside the full digits', () => {
    showValue({ value: 1948466.99, prefix: '$' });

    const short = screen.getByText('$1.9M');
    expect(short.getAttribute('aria-hidden')).toBe('true');
    expect(short.className).toContain('md:hidden');
    expect(screen.getByText('$1,948,466.99').className).toContain('max-md:sr-only');
  });

  it('shows a rise in the destructive subtle colour when lower is better', () => {
    showValue({ value: 382, previous: 344, change: 11, lowerIsBetter: true });

    const badge = screen.getByText('+11%').closest('[data-change]');
    expect(badge?.className).toContain('bg-destructive-subtle');
    expect(badge?.className).toContain('text-destructive-subtle-foreground');
  });

  it('shows a fall in the success subtle colour when lower is better', () => {
    showValue({ value: 300, previous: 344, change: -12.79, lowerIsBetter: true });

    expect(screen.getByText('-12.79%').closest('[data-change]')?.className).toContain('bg-success-subtle');
  });

  it('says there is nothing to compare with when the range before was empty', () => {
    showValue({ value: 5, previous: 0, change: null });

    expect(screen.getByText('No earlier data to compare with.')).toBeTruthy();
  });
});

describe('a card cell', () => {
  it('stretches to its row with height as a minimum', () => {
    expect(cellClass(widget).split(' ')).toContain('min-h-40');
    expect(cellClass({ ...widget, height: 'double' }).split(' ')).toContain('min-h-80');
    expect(cellClass(widget)).not.toMatch(/(^|\s)h-40\b/);
  });

  it('sits a value card two across below 768 px and any other card full width', () => {
    expect(cellClass(widget).split(' ')).toContain('col-span-6');
    expect(cellClass({ ...widget, component: 'trend-widget' }).split(' ')).toContain('col-span-12');
  });
});
