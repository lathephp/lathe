import { formatNumber, useTranslation } from '@lathephp/core';
import { ChartContainer, LabelList, Pie, PieChart } from '@lathephp/ui/chart';
import type { WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import type { Segment } from './PieCard';
import { useChartTooltip } from './tooltip';

const format = (value: number) => formatNumber(value);

/** Shares of a whole as a small donut beside a legend, or as proportion bars. */
export function PartitionCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const segments = (value as { value: Segment[] } | null)?.value ?? [];
  const tooltip = useChartTooltip(widget, { hideLabel: true }, format);

  if (segments.length === 0) {
    return <NoData />;
  }

  const rows = segments.map((segment) => [segment.label, format(segment.value), `${String(segment.percentage)}%`]);
  const legend = (
    <ul className="min-w-0 flex-1 space-y-1.5 overflow-y-auto text-xs">
      {segments.map((segment) => (
        <li key={segment.label} className="flex items-center gap-2">
          <span className="size-2.5 shrink-0 rounded-[2px]" style={{ backgroundColor: segment.color }} />
          <span className="min-w-0 truncate font-medium">{segment.label}</span>
          <span className="ms-auto shrink-0 text-muted-foreground tabular-nums">{format(segment.value)}</span>
          <span className="shrink-0 text-muted-foreground tabular-nums">{`(${String(segment.percentage)}%)`}</span>
        </li>
      ))}
    </ul>
  );

  return (
    <ChartFrame name={widget.name} columns={[__('Label'), __('Value'), __('Percentage')]} rows={rows}>
      {widget.asBar === true ? (
        <div className="space-y-2">
          <div className="flex h-3 overflow-hidden rounded-full bg-muted">
            {segments.map((segment) => (
              <div key={segment.label} className="h-full" style={{ width: `${String(segment.percentage)}%`, backgroundColor: segment.color }} />
            ))}
          </div>
          {legend}
        </div>
      ) : (
        <div className="flex h-full items-center gap-4">
          <ChartContainer className="aspect-square h-full max-h-24 shrink-0">
            <PieChart>
              {tooltip}
              <Pie data={segments.map((segment) => ({ name: segment.label, value: segment.value, fill: segment.color }))} dataKey="value" nameKey="name" innerRadius="60%" outerRadius="90%" stroke="var(--background)" strokeWidth={2}>
                {widget.showLabels === true && <LabelList dataKey="name" position="outside" className="fill-foreground" stroke="none" fontSize={10} />}
              </Pie>
            </PieChart>
          </ChartContainer>
          {legend}
        </div>
      )}
    </ChartFrame>
  );
}
