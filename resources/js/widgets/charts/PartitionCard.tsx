import { useTranslation } from '@lathephp/core';
import { ChartContainer, LabelList, Pie, PieChart } from '@lathephp/ui/chart';
import { cn } from '../../lib/cn';
import { chartFormatter } from '../number';
import type { NumberDisplay, WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import type { Segment } from './PieCard';
import { useChartTooltip } from './tooltip';

/** Shares of a whole as a donut beside a legend, or as proportion bars. A phone always shows the bars. */
export function PartitionCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as ({ value: Segment[] } & Partial<NumberDisplay>) | null;
  const format = chartFormatter(result);
  const segments = result?.value ?? [];
  const tooltip = useChartTooltip(widget, { hideLabel: true }, format);

  if (segments.length === 0) {
    return <NoData />;
  }

  const asBar = widget.asBar === true;
  const total = segments.reduce((sum, segment) => sum + segment.value, 0);
  const rows = segments.map((segment) => [segment.label, format(segment.value), `${String(segment.percentage)}%`]);
  const bar = (
    <div className={cn('flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-muted', !asBar && 'md:hidden')}>
      {segments.map((segment) => (
        <div key={segment.label} className="h-full" style={{ width: `${String(segment.percentage)}%`, backgroundColor: segment.color }} />
      ))}
    </div>
  );
  const legend = (
    // The legend scrolls within the card, so it takes keyboard focus to scroll by key.
    <div role="region" aria-label={__(':name legend', { name: widget.name })} tabIndex={0} className="max-h-full min-w-0 flex-1 overflow-y-auto">
      <ul className={cn('grid grid-cols-2 gap-x-3 gap-y-1 text-[13px]', !asBar && 'md:flex md:flex-col md:gap-2')}>
        {segments.map((segment) => (
          <li key={segment.label} className="flex min-w-0 items-center gap-1.5 md:gap-2">
            <span className="size-2 shrink-0 rounded-full" style={{ backgroundColor: segment.color }} />
            <span className="min-w-12 flex-1 truncate">{segment.label}</span>
            <span className="shrink-0 font-medium tabular-nums">{format(segment.value)}</span>
            <span className={cn('w-11 shrink-0 text-end text-muted-foreground tabular-nums max-md:hidden', asBar && 'hidden')}>{`${String(segment.percentage)}%`}</span>
          </li>
        ))}
      </ul>
    </div>
  );

  return (
    <ChartFrame name={widget.name} columns={[__('Label'), __('Value'), __('Percentage')]} rows={rows}>
      {asBar ? (
        <div className="flex flex-col gap-2">
          {bar}
          {legend}
        </div>
      ) : (
        <div className="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
          <div className="relative size-[132px] shrink-0 max-md:hidden">
            <ChartContainer className="size-full">
              <PieChart>
                {tooltip}
                <Pie
                  data={segments.map((segment) => ({ name: segment.label, value: segment.value, fill: segment.color }))}
                  dataKey="value"
                  nameKey="name"
                  innerRadius={44}
                  outerRadius={60}
                  startAngle={90}
                  endAngle={-270}
                  stroke="var(--card)"
                  strokeWidth={2}
                >
                  {widget.showLabels === true && <LabelList dataKey="name" position="outside" className="fill-foreground" stroke="none" fontSize={10} />}
                </Pie>
              </PieChart>
            </ChartContainer>
            {/* The whole the shares add up to, in the hole of the donut. */}
            <div aria-hidden="true" className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
              <span className="text-lg leading-6 font-[650] tabular-nums">{format(total)}</span>
              <span className="text-[11px] text-muted-foreground">{__('Total')}</span>
            </div>
          </div>
          {bar}
          {legend}
        </div>
      )}
    </ChartFrame>
  );
}
