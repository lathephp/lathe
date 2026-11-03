import { render, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { PartitionCard } from '../widgets/charts/PartitionCard';
import { RadialCard } from '../widgets/charts/RadialCard';
import type { WidgetDescriptor } from '../widgets/types';

// jsdom does no layout, so these pin the classes that make the browser give the label its room.
const widget: WidgetDescriptor = {
  name: 'Orders',
  uriKey: 'orders',
  component: 'partition-widget',
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

const segments = [
  { label: 'Shipped', value: 120, percentage: 60, color: '#22c55e' },
  { label: 'Cancelled', value: 80, percentage: 40, color: '#ef4444' },
];

function legend() {
  render(<PartitionCard widget={widget} value={{ value: segments }} />);

  return screen.getByRole('region', { name: 'Orders legend' });
}

const classes = (element: HTMLElement) => element.className.split(/\s+/);

describe('a donut legend in a narrow card', () => {
  it('keeps at least 6 characters of each label beside the donut', () => {
    const region = legend();

    for (const name of ['Shipped', 'Cancelled']) {
      expect(classes(within(region).getByText(name))).toContain('md:min-w-[6ch]');
    }
  });

  it('lets a bar legend label give way, since its two columns sit side by side', () => {
    render(<PartitionCard widget={{ ...widget, asBar: true }} value={{ value: segments }} />);
    const region = screen.getByRole('region', { name: 'Orders legend' });

    expect(classes(within(region).getByText('Shipped'))).not.toContain('md:min-w-[6ch]');
  });

  it('trades the donut for the bar in a card under 16rem, so the legend keeps its values', () => {
    const { container } = render(<PartitionCard widget={widget} value={{ value: segments }} />);
    const donut = container.querySelector('.size-\\[132px\\]') as HTMLElement;
    const bar = container.querySelector('.rounded-full.bg-muted') as HTMLElement;

    expect(donut.closest('.\\@container\\/part')).not.toBeNull();
    expect(classes(donut)).toContain('@max-[16rem]/part:hidden');
    expect(classes(bar)).toContain('@max-[16rem]/part:flex');
  });

  it('hides the percentage column first when the legend is under 12rem', () => {
    const region = legend();

    expect(classes(region)).toContain('@container');
    expect(classes(within(region).getByText('60%'))).toContain('@max-[12rem]:hidden');
    expect(within(region).getByText('Shipped').className).not.toMatch(/hidden/);
  });

  it('shows every value on a phone, where the label floor is off and the label gives way', () => {
    const region = legend();

    for (const [name, value] of [
      ['Shipped', '120'],
      ['Cancelled', '80'],
    ] as const) {
      const label = classes(within(region).getByText(name));

      expect(label).toContain('min-w-0');
      expect(label).not.toContain('min-w-[6ch]');
      expect(label).toContain('md:min-w-[6ch]');
      expect(within(region).getByText(value).className).not.toMatch(/hidden/);
    }
  });
});

describe('a radial gauge in a tall card', () => {
  it('caps the square chart at its parent width', () => {
    const gauge = { value: 40, target: 80, percentage: 50, startAngle: 180, endAngle: 0, showTrack: true, showLabels: false, showGrid: false, color: 'var(--warning)' };
    const { container } = render(<RadialCard widget={{ ...widget, component: 'radial-widget' }} value={gauge} />);
    const chart = container.querySelector<HTMLElement>('[data-slot="chart"]');

    expect(chart).not.toBeNull();
    expect(classes(chart as HTMLElement)).toEqual(expect.arrayContaining(['aspect-square', 'h-full', 'max-w-full']));
  });
});
