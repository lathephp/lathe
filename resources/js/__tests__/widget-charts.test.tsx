import { render, screen, waitFor, within } from '@testing-library/react';
import { cloneElement, type ReactElement } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { BarCard } from '../widgets/charts/BarCard';
import { PartitionCard } from '../widgets/charts/PartitionCard';
import { PieCard } from '../widgets/charts/PieCard';
import { RadarCard } from '../widgets/charts/RadarCard';
import { RadialCard } from '../widgets/charts/RadialCard';
import { useChartTooltip } from '../widgets/charts/tooltip';
import { TrendCard } from '../widgets/charts/TrendCard';
import type { WidgetDescriptor } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';

// Set when the chart module is first imported, which only a dynamic import does.
const charts = vi.hoisted(() => ({ loaded: false }));

vi.mock('../widgets/charts', async (importOriginal) => {
  charts.loaded = true;

  return importOriginal();
});

afterEach(() => {
  vi.restoreAllMocks();
});

function descriptor(uriKey: string, component: string): WidgetDescriptor {
  return {
    name: uriKey === 'orders-by-status' ? 'Orders by Status' : 'Total Orders',
    uriKey,
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
  };
}

const bars = {
  bars: [
    { label: 'Paid', value: 42, color: 'var(--chart-1)' },
    { label: 'Refunded', value: 7, color: 'var(--chart-2)' },
  ],
};

const total = {
  value: 49,
  previous: null,
  change: null,
  previousLabel: null,
  prefix: null,
  suffix: null,
  suffixInflection: true,
  format: null,
  allowZeroResult: false,
  copyable: false,
};

function answer() {
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const key = /widgets\/([^/?]+)/.exec(url)?.[1] ?? '';
    const value = key === 'orders-by-status' ? bars : total;

    return Promise.resolve(new Response(JSON.stringify({ name: key, uriKey: key, component: '', value }), { status: 200 }));
  });
}

describe('a bar card', () => {
  it('loads no chart code for a page without chart cards', async () => {
    answer();
    render(<WidgetGrid widgets={[descriptor('total-orders', 'value-widget')]} valueUrl="/lathe-api/dashboards/main/widgets" />);

    await screen.findByText('49');

    expect(charts.loaded).toBe(false);
  });

  it('names the chart after the widget and lists each bar in a hidden table', async () => {
    answer();
    render(<WidgetGrid widgets={[descriptor('orders-by-status', 'bar-widget')]} valueUrl="/lathe-api/dashboards/main/widgets" />);

    const chart = await screen.findByLabelText('Orders by Status');
    const table = within(chart).getByRole('table');

    expect(table.closest('.sr-only')).not.toBeNull();
    // Recharts makes its SVG focusable; nothing focusable may sit under aria-hidden.
    expect(chart.querySelector('[aria-hidden="true"] [tabindex]')).toBeNull();
    expect([...table.querySelectorAll('tbody tr')].map((row) => [...row.children].map((cell) => cell.textContent))).toEqual([
      ['Paid', '42'],
      ['Refunded', '7'],
    ]);
    await waitFor(() => {
      expect(charts.loaded).toBe(true);
    });
  });
});

// With reduced motion Recharts skips its animations, so a chart draws on the first render.
function reduceMotion() {
  vi.stubGlobal('matchMedia', (query: string) => ({ matches: query.includes('reduce'), addEventListener: () => undefined, removeEventListener: () => undefined }));
}

function widgetOf(component: string, options: Record<string, unknown> = {}): WidgetDescriptor {
  return { ...descriptor('chart', component), name: 'Chart', ...options };
}

function svg(): SVGElement {
  const found = document.querySelector<SVGElement>('svg.recharts-surface');

  if (found === null) {
    throw new Error('No chart was drawn.');
  }

  return found;
}

const attributes = (selector: string, name: string) => [...svg().querySelectorAll(selector)].map((node) => node.getAttribute(name));

function hiddenRows(): (string | null)[][] {
  return [...screen.getByRole('figure').querySelectorAll('tbody tr')].map((row) => [...row.children].map((cell) => cell.textContent));
}

const segments = [
  { label: 'Paid', value: 20, percentage: 66.67, color: 'var(--chart-1)' },
  { label: 'Open', value: 10, percentage: 33.33, color: '#ef4444' },
];

const trend = {
  trend: { '2026-10-14T13:00:00+00:00': 4, '2026-10-14T14:00:00+00:00': 7 },
  value: 7,
  series: null,
  labels: null,
  prefix: '$',
  suffix: null,
  suffixInflection: true,
  format: null,
};

describe('the chart cards', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it.each([
    ['trend-widget', TrendCard, { ...trend, trend: {}, value: null }],
    ['bar-widget', BarCard, { bars: [] }],
    ['pie-widget', PieCard, { value: [], innerRadius: 0 }],
    ['partition-widget', PartitionCard, { value: [] }],
    ['radar-widget', RadarCard, { axes: [], datasets: [] }],
    ['radial-widget', RadialCard, null],
  ] as const)('shows No Data for a %s with no points', (component, Card, value) => {
    render(<Card widget={widgetOf(component)} value={value} />);

    expect(screen.getByText('No Data').tagName).toBe('P');
    expect(screen.queryByRole('figure')).toBeNull();
  });

  it('colours bars from the server, a negative bar in negativeColor, and labels each bar', () => {
    reduceMotion();
    render(
      <BarCard
        widget={widgetOf('bar-widget', { showLabels: true })}
        value={{ bars: [{ label: 'Gain', value: 5, color: 'var(--chart-1)' }, { label: 'Loss', value: -3, color: '#ef4444' }], negativeColor: '#000000' }}
      />,
    );

    expect(attributes('path.recharts-rectangle', 'fill')).toEqual(['var(--chart-1)', '#000000']);
    expect([...svg().querySelectorAll('.recharts-label-list text')].map((node) => node.textContent)).toEqual(['5', '-3']);
    expect(svg().querySelector('.recharts-reference-line')).not.toBeNull();
  });

  it('lays bars out horizontally and stacks the series', () => {
    reduceMotion();
    render(
      <BarCard
        widget={widgetOf('bar-widget')}
        value={{ bars: [], horizontal: true, stacked: true, series: { Web: [{ label: 'Mon', value: 2 }], Shop: [{ label: 'Mon', value: 3 }] } }}
      />,
    );

    const [web, shop] = [...svg().querySelectorAll('path.recharts-rectangle')];

    // Stacked horizontal bars share one row, the second starting where the first ends.
    expect(web?.getAttribute('y')).toBe(shop?.getAttribute('y'));
    expect(Number(shop?.getAttribute('x'))).toBeGreaterThan(Number(web?.getAttribute('x')));
    expect(hiddenRows()).toEqual([['Mon', '2', '3']]);
  });

  it('draws a donut with its total and the first segment held active with a stroke of 5', () => {
    reduceMotion();
    render(<PieCard widget={widgetOf('pie-widget', { activeDonut: { strokeWidth: 5, activeIndex: 0 }, showTotal: true })} value={{ value: segments, innerRadius: 0.5 }} />);

    expect(attributes('path.recharts-sector', 'stroke-width')).toEqual(['5', '5']);
    expect(attributes('path.recharts-sector', 'fill')).toEqual(['var(--chart-1)', '#ef4444']);
    // The active sector reaches 6 px past the radius: 80% of the 95 px half side the legend leaves.
    expect(attributes('path.recharts-sector', 'd').map((path) => /A ([\d.]+),/.exec(path ?? '')?.[1])).toEqual(['82', '76']);
    expect(svg().textContent).toContain('30Total');
    expect(hiddenRows()).toEqual([
      ['Paid', '20', '66.67%'],
      ['Open', '10', '33.33%'],
    ]);
  });

  it.each([
    ['inline', ['Paid', 'Open']],
    ['custom', ['Paid: 20', 'Open: 10']],
  ])('labels pie segments in %s mode', (labelMode, labels) => {
    reduceMotion();
    render(<PieCard widget={widgetOf('pie-widget', { labelMode })} value={{ value: segments, innerRadius: 0 }} />);

    expect([...svg().querySelectorAll('.recharts-pie-label-text')].map((node) => node.textContent)).toEqual(labels);
  });

  it('lists pie labels on the segments in list mode', () => {
    reduceMotion();
    render(<PieCard widget={widgetOf('pie-widget', { labelMode: 'list' })} value={{ value: segments, innerRadius: 0 }} />);

    expect([...svg().querySelectorAll('.recharts-label-list text')].map((node) => node.textContent)).toEqual(['Paid', 'Open']);
    expect(svg().querySelector('.recharts-pie-label-text')).toBeNull();
  });

  it.each([
    ['polygon', '.recharts-polar-grid-concentric-polygon'],
    ['circle', '.recharts-polar-grid-concentric-circle'],
  ])('draws a %s radar grid with its fill opacity and dots', (gridType, selector) => {
    reduceMotion();
    render(
      <RadarCard
        widget={widgetOf('radar-widget')}
        value={{
          axes: [
            { key: 'speed', label: 'Speed' },
            { key: 'power', label: 'Power' },
            { key: 'range', label: 'Range' },
          ],
          datasets: [{ name: 'Value', data: [3, 5, 4] }],
          dots: true,
          gridType,
          fillOpacity: 0.3,
          lineOnly: false,
          gridNone: false,
          radialLines: true,
          gridFilled: false,
          showLegend: false,
        }}
      />,
    );

    expect(svg().querySelector(selector)).not.toBeNull();
    expect(attributes('.recharts-radar-polygon path', 'fill-opacity')).toEqual(['0.3']);
    expect(svg().querySelectorAll('.recharts-radar-dots .recharts-dot')).toHaveLength(3);
    expect(hiddenRows()).toEqual([
      ['Speed', '3'],
      ['Power', '5'],
      ['Range', '4'],
    ]);
  });

  it('draws a radial gauge in its colour, over the track when shown, with the value in the centre', () => {
    reduceMotion();
    const gauge = { value: 40, target: 80, percentage: 50, startAngle: 180, endAngle: 0, showTrack: true, showLabels: false, showGrid: false, color: 'var(--warning)' };
    const { unmount } = render(<RadialCard widget={widgetOf('radial-widget')} value={gauge} />);

    expect(attributes('.recharts-radial-bar-sectors path', 'fill')).toEqual(['var(--warning)']);
    expect(svg().querySelector('.recharts-radial-bar-background-sector')).not.toBeNull();
    expect(svg().textContent).toContain('40/ 80');
    expect(hiddenRows()).toEqual([
      ['Value', '40'],
      ['Target', '80'],
    ]);

    unmount();
    render(<RadialCard widget={widgetOf('radial-widget')} value={{ ...gauge, showTrack: false }} />);

    expect(svg().querySelector('.recharts-radial-bar-background-sector')).toBeNull();
  });

  it('starts a radial gauge at its start angle', () => {
    reduceMotion();
    render(
      <RadialCard
        widget={widgetOf('radial-widget')}
        value={{ value: 80, target: 80, percentage: 100, startAngle: 90, endAngle: -270, showTrack: false, showLabels: false, showGrid: false, color: 'var(--success)' }}
      />,
    );

    // 90 degrees is the top of the circle: the sector starts above the centre, at its x.
    const [x, y] = (/M\s*([\d.]+),([\d.]+)/.exec(attributes('.recharts-radial-bar-sectors path', 'd')[0] ?? '') ?? []).slice(1).map(Number);

    expect(x).toBeCloseTo(160, 0);
    expect(y).toBeLessThan(100);
  });

  it('draws a trend as an area by default, with the headline and each bucket in the hidden table', () => {
    reduceMotion();
    render(<TrendCard widget={widgetOf('trend-widget')} value={trend} />);

    expect(screen.getByText('$7', { selector: 'p' }).className).toContain('text-3xl');
    expect(svg().querySelector('.recharts-area')).not.toBeNull();
    expect(svg().querySelector('.recharts-line')).toBeNull();
    expect(hiddenRows()).toEqual([
      ['Oct 14, 13:00', '$4'],
      ['Oct 14, 14:00', '$7'],
    ]);
  });

  it('draws a line only, with dots, a step curve and 2 PM style hours', () => {
    reduceMotion();
    render(<TrendCard widget={widgetOf('trend-widget', { lineOnly: true, showDots: true, curveType: 'step', twelveHourTime: true })} value={trend} />);

    expect(svg().querySelector('.recharts-area')).toBeNull();
    expect(svg().querySelectorAll('.recharts-line-dots .recharts-dot')).toHaveLength(2);
    // A step between two points takes three straight segments: across, up, across.
    expect(svg().querySelector('.recharts-line-curve')?.getAttribute('d')).toBe('M4,102L160,102L160,28.5L316,28.5');
    expect(hiddenRows().map((row) => row[0])).toEqual(['Oct 14, 1:00 PM', 'Oct 14, 2:00 PM']);
  });

  it('stacks the trend series', () => {
    reduceMotion();
    render(<TrendCard widget={widgetOf('trend-widget', { stacked: true })} value={{ ...trend, series: { Web: { '2026-10': 2 }, Shop: { '2026-10': 3 } } }} />);

    expect(svg().querySelectorAll('.recharts-area')).toHaveLength(2);
    expect(hiddenRows()).toEqual([['2026-10', '$2', '$3']]);
  });

  it('shows the tooltip with the options of the descriptor', () => {
    function Probe() {
      const tooltip = useChartTooltip(widgetOf('bar-widget', { tooltip: { indicator: 'line', label: 'Orders', valuePrefix: '$', valueSuffix: ' net', showTotal: true } }));
      const content = (tooltip.props as { content: ReactElement<Record<string, unknown>> }).content;

      return cloneElement(content, {
        active: true,
        label: 'Mon',
        payload: [
          { name: 'Web', value: 2, color: 'red', dataKey: 's0', payload: {} },
          { name: 'Shop', value: 3, color: 'blue', dataKey: 's1', payload: {} },
        ],
      });
    }

    const { container } = render(<Probe />);

    expect(container.textContent).toBe('OrdersWeb$2 netShop$3 netTotal$5 net');
    expect(container.querySelectorAll('[data-indicator="line"]')).toHaveLength(2);
  });

  it('draws a partition as proportion bars', () => {
    render(<PartitionCard widget={widgetOf('partition-widget', { asBar: true })} value={{ value: segments }} />);

    const bars = [...screen.getByRole('figure').querySelectorAll<HTMLElement>('div[style*="width"]')];

    expect(bars.map((bar) => bar.style.width)).toEqual(['66.67%', '33.33%']);
    expect(hiddenRows()).toEqual([
      ['Paid', '20', '66.67%'],
      ['Open', '10', '33.33%'],
    ]);
  });
});
