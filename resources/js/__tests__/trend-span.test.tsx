import { render } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TrendCard } from '../widgets/charts/TrendCard';
import type { WidgetDescriptor } from '../widgets/types';

afterEach(() => {
  vi.unstubAllGlobals();
});

const widget = {
  name: 'Revenue per day',
  uriKey: 'revenue',
  component: 'trend-widget',
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
} as WidgetDescriptor;

/** One day key per day, from `from` for `days` days. */
function payload(from: string, days: number) {
  const trend: Record<string, number> = {};

  for (let day = 0; day < days; day++) {
    trend[new Date(Date.parse(`${from}T00:00:00Z`) + day * 86_400_000).toISOString().slice(0, 10)] = 0;
  }

  return { trend, value: 0, series: null, labels: null, prefix: '$', suffix: null, suffixInflection: true, format: null };
}

function header(from: string, days: number): string {
  vi.stubGlobal('matchMedia', () => ({ matches: true, addEventListener: () => undefined, removeEventListener: () => undefined }));
  const { container } = render(<TrendCard widget={widget} value={payload(from, days)} />);

  return container.querySelector('p')?.textContent ?? '';
}

describe('the trend card span', () => {
  it('shows both years when the span crosses a year', () => {
    expect(header('2025-11-04', 365)).toContain('Nov 4, 2025 – Nov 3, 2026');
  });

  it('stays short inside one year', () => {
    const text = header('2026-10-05', 30);

    expect(text).toContain('Oct 5 – Nov 3');
    expect(text).not.toContain('2026');
  });
});
