import { ChartContainer, Bar, BarChart } from '@lathephp/ui/chart';
import { render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { PartitionCard } from '../widgets/charts/PartitionCard';
import { cellClass } from '../widgets/layout';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

const widget: WidgetDescriptor = {
  name: 'Orders',
  uriKey: 'orders',
  component: 'value-widget',
  width: '1/2',
  columns: 6,
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

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

describe('a card on a phone', () => {
  it('spans the full width when it is half or wider, and sits two across when it is a third or less', () => {
    for (const component of ['value-widget', 'progress-widget']) {
      expect(cellClass({ ...widget, component, columns: 6 }).split(' ')).toContain('col-span-12');
      expect(cellClass({ ...widget, component, columns: 6 }).split(' ')).not.toContain('col-span-6');
      expect(cellClass({ ...widget, component, columns: 4 }).split(' ')).toContain('col-span-6');
    }
  });
});

describe('the dashboard range control on a phone', () => {
  it('scrolls instead of squeezing, and keeps each label whole', () => {
    renderPage(
      'Dashboard',
      {
        dashboard: {
          uriKey: 'main',
          name: 'Main',
          description: null,
          customizable: false,
          customized: false,
          ranges: [
            { value: 7, label: '7 days' },
            { value: 365, label: '12 months' },
          ],
          range: 7,
        },
        widgets: [widget],
        'widgetValue:orders': { value: 1 },
      },
      {},
    );

    const group = screen.getByRole('radiogroup', { name: 'Range' });
    const button = within(group).getByRole('radio', { name: '12 months' });

    expect(group.className).toContain('max-md:overflow-x-auto');
    expect(button.className).toContain('shrink-0');
    expect(button.className).not.toContain('truncate');
  });
});

describe('a donut legend', () => {
  it('lets the label give way, so the value and the share keep their full text', () => {
    const segments = [{ label: 'A very long category name', value: 1234567, percentage: 40, color: '#ef4444' }];

    render(<PartitionCard widget={{ ...widget, component: 'partition-widget' }} value={{ value: segments }} />);

    const legend = screen.getByRole('region', { name: 'Orders legend' });
    const label = within(legend).getByText('A very long category name');
    const value = within(legend).getByText('1,234,567');

    expect(label.className).toContain('min-w-0');
    expect(label.className).not.toContain('min-w-12');
    expect(value.className).toContain('shrink-0');
    expect(value.className).not.toContain('truncate');
  });
});

describe('a chart in a box with no size', () => {
  it('draws nothing and does not warn about a zero width', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    vi.stubGlobal(
      'ResizeObserver',
      class {
        constructor(private readonly callback: ResizeObserverCallback) {}
        observe(target: Element) {
          this.callback([{ target, contentRect: new DOMRect(0, 0, 0, 0) } as ResizeObserverEntry], this);
        }
        unobserve() {}
        disconnect() {}
      },
    );
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue(new DOMRect(0, 0, 0, 0));

    render(
      <ChartContainer>
        <BarChart data={[{ x: 'a', y: 1 }]}>
          <Bar dataKey="y" />
        </BarChart>
      </ChartContainer>,
    );

    expect(document.querySelector('svg.recharts-surface')).toBeNull();
    expect(warn.mock.calls.map((call) => String(call[0])).filter((message) => message.includes('width(')).length).toBe(0);
  });
});
