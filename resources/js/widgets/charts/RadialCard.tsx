import { useTranslation } from '@lathephp/core';
import { ChartContainer, Label, LabelList, PolarAngleAxis, PolarGrid, PolarRadiusAxis, RadialBar, RadialBarChart } from '@lathephp/ui/chart';
import { chartFormatter } from '../number';
import type { NumberDisplay, WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import { seriesColor } from './palette';
import { useChartTooltip } from './tooltip';

/** RadialResult::jsonSerialize(). */
export interface RadialPayload extends Partial<NumberDisplay> {
  value: number;
  target: number;
  percentage: number;
  startAngle: number;
  endAngle: number;
  showTrack: boolean;
  showLabels: boolean;
  showGrid: boolean;
  color: string;
  caption?: string;
  label?: string;
  segments?: { label: string; value: number; color: string; target?: number; percentage?: number }[];
  stacked?: { name: string; value: number }[];
}

/** A value against its target as a gauge, one ring per segment, or items sharing one ring. */
export function RadialCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as RadialPayload | null;
  const format = chartFormatter(result);
  const tooltip = useChartTooltip(widget, { hideLabel: true }, format);

  if (result === null) {
    return <NoData />;
  }

  const segments = result.segments ?? [];
  const stacked = result.stacked ?? [];
  const targets = segments.some((segment) => segment.target !== undefined);
  const rows =
    stacked.length > 0
      ? stacked.map((item) => [item.name, format(item.value)])
      : segments.length > 0
        ? segments.map((segment) => [segment.label, format(segment.value)])
        : [
            [__('Value'), format(result.value)],
            [__('Target'), format(result.target)],
          ];
  // The gauge's centre text: the value, and its label or the target under it.
  const centre = (top: string, bottom: string) => (
    <PolarRadiusAxis tick={false} tickLine={false} axisLine={false}>
      <Label
        content={({ viewBox }) => {
          if (viewBox === undefined || !('cx' in viewBox) || !('cy' in viewBox)) {
            return null;
          }

          const { cx } = viewBox;
          const { cy } = viewBox;

          return (
            <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle">
              <tspan x={cx} y={cy} className="fill-foreground text-3xl font-bold">
                {top}
              </tspan>
              <tspan x={cx} y={cy + 24} className="fill-muted-foreground">
                {bottom}
              </tspan>
            </text>
          );
        }}
      />
    </PolarRadiusAxis>
  );

  let chart;

  if (stacked.length > 0) {
    const row = Object.fromEntries(stacked.map((item, index) => [`s${String(index)}`, item.value]));

    chart = (
      <RadialBarChart data={[row]} startAngle={result.startAngle} endAngle={result.endAngle} innerRadius="70%" outerRadius="100%">
        {tooltip}
        {centre(format(stacked.reduce((sum, item) => sum + item.value, 0)), __('Total'))}
        {stacked.map((item, index) => (
          <RadialBar key={item.name} name={item.name} dataKey={`s${String(index)}`} stackId="stack" cornerRadius={5} fill={seriesColor(index)} />
        ))}
      </RadialBarChart>
    );
  } else if (segments.length > 0) {
    chart = (
      <RadialBarChart
        data={segments.map((segment) => ({ name: segment.label, value: targets ? (segment.percentage ?? 0) : segment.value, fill: segment.color }))}
        startAngle={result.startAngle}
        endAngle={result.endAngle}
        innerRadius="30%"
        outerRadius="100%"
      >
        {tooltip}
        {result.showGrid && <PolarGrid gridType="circle" />}
        <RadialBar dataKey="value" background={result.showTrack}>
          {result.showLabels && <LabelList position="insideStart" dataKey="name" className="fill-white" fontSize={11} />}
        </RadialBar>
      </RadialBarChart>
    );
  } else {
    // The angle axis runs 0 to 100, so the bar covers the share and the track the whole arc.
    chart = (
      <RadialBarChart data={[{ name: widget.name, value: Math.min(100, result.percentage), fill: result.color }]} startAngle={result.startAngle} endAngle={result.endAngle} innerRadius="75%" outerRadius="100%">
        <PolarAngleAxis type="number" domain={[0, 100]} tick={false} tickLine={false} axisLine={false} />
        <RadialBar dataKey="value" background={result.showTrack} cornerRadius={10} />
        {centre(format(result.value), result.label ?? `/ ${format(result.target)}`)}
      </RadialBarChart>
    );
  }

  return (
    <div className="flex h-full flex-col items-center gap-1">
      <ChartFrame name={widget.name} columns={[__('Label'), __('Value')]} rows={rows} className="min-h-0 flex-1">
        <ChartContainer className="mx-auto aspect-square h-full">{chart}</ChartContainer>
      </ChartFrame>
      {result.caption !== undefined && <p className="text-xs text-muted-foreground">{result.caption}</p>}
    </div>
  );
}
