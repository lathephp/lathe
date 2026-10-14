import { formatDateTime, useTranslation } from '@lathephp/core';
import { Area, AreaChart, CartesianGrid, ChartContainer, ChartLegend, ChartLegendContent, LabelList, Line, LineChart, XAxis, YAxis } from '@lathephp/ui/chart';
import { formatMetric } from '../number';
import type { NumberDisplay, WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import { seriesColor } from './palette';
import { useChartTooltip } from './tooltip';

/** TrendResult::payload(). */
export interface TrendPayload extends NumberDisplay {
  trend: Record<string, number>;
  value: number | null;
  series: Record<string, Record<string, number>> | null;
  labels: Record<string, string> | null;
}

type Curve = 'monotone' | 'linear' | 'step' | 'natural';

/** A minute or hour bucket is an ISO 8601 instant; it shows as a date and a time, 2 PM style when asked. */
export function bucketLabel(key: string, twelveHourTime: boolean): string {
  if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(key)) {
    return key;
  }

  return formatDateTime(key, { dateStyle: undefined, timeStyle: undefined, month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: twelveHourTime });
}

/** A number per time bucket as an area or a line, one per series. */
export function TrendCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as TrendPayload | null;
  const twelveHourTime = widget.twelveHourTime === true;
  const lineOnly = widget.lineOnly === true;
  const stacked = widget.stacked === true;
  const expand = widget.stackedExpand === true;
  const showAxes = widget.showAxes === true;
  const showDots = widget.showDots === true;
  const dotColors = widget.dotColors === true;
  const curve = (typeof widget.curveType === 'string' ? widget.curveType : 'monotone') as Curve;
  const format = (number: number) => (result === null ? String(number) : formatMetric(number, result));
  const tooltip = useChartTooltip(widget, {}, format);
  const names = Object.keys(result?.series ?? {});
  const keys = result === null ? [] : names.length > 0 ? [...new Set(names.flatMap((name) => Object.keys(result.series?.[name] ?? {})))] : Object.keys(result.trend);

  if (result === null || keys.length === 0) {
    return <NoData />;
  }

  // One row per bucket: its label, then a value per series (s0, s1, ...) or the one value.
  const data = keys.map((key) => ({
    label: result.labels?.[key] ?? bucketLabel(key, twelveHourTime),
    ...(names.length > 0
      ? Object.fromEntries(names.map((name, index) => [`s${String(index)}`, result.series?.[name]?.[key] ?? 0]))
      : { value: result.trend[key] ?? 0 }),
  }));
  const lines = names.length > 0 ? names.map((name, index) => ({ key: `s${String(index)}`, name, color: seriesColor(index) })) : [{ key: 'value', name: widget.name, color: seriesColor(0) }];
  const rows = data.map((row) => [row.label, ...lines.map((line) => format(Number((row as Record<string, unknown>)[line.key])))]);
  const dot = (color: string) => {
    // Each point takes the next palette colour.
    if (dotColors) {
      return ({ cx, cy, index }: { cx?: number; cy?: number; index?: number }) => (
        <circle key={index} cx={cx} cy={cy} r={4} fill={seriesColor(index ?? 0)} />
      );
    }

    if (widget.dotStyle === 'custom') {
      return { r: 4, fill: 'var(--background)', stroke: color, strokeWidth: 2 };
    }

    return showDots ? { r: 3, fill: color } : false;
  };
  const Chart = lineOnly ? LineChart : AreaChart;

  return (
    <div className="flex h-full flex-col gap-1">
      {result.value !== null && <p className="text-3xl font-semibold tracking-tight tabular-nums">{format(result.value)}</p>}
      <ChartFrame name={widget.name} columns={[__('Label'), ...lines.map((line) => line.name)]} rows={rows} className="min-h-0 flex-1">
        <ChartContainer className="h-full w-full">
          <Chart accessibilityLayer data={data} margin={{ top: widget.showLabels === true ? 20 : 4, right: 4, bottom: 0, left: 4 }} stackOffset={expand ? 'expand' : undefined}>
            <CartesianGrid vertical={false} strokeDasharray="3 3" />
            <XAxis dataKey="label" hide={!showAxes} tickLine={false} axisLine={false} tickMargin={8} />
            <YAxis hide={!showAxes} tickLine={false} axisLine={false} tickMargin={8} tickCount={3} />
            {tooltip}
            {names.length > 0 && <ChartLegend content={<ChartLegendContent />} />}
            {lines.map((line) =>
              lineOnly ? (
                <Line key={line.key} dataKey={line.key} name={line.name} type={curve} stroke={line.color} strokeWidth={2} dot={dot(line.color)}>
                  {widget.showLabels === true && <LabelList dataKey={typeof widget.labelKey === 'string' ? widget.labelKey : line.key} position="top" offset={10} className="fill-foreground" fontSize={11} />}
                </Line>
              ) : (
                <Area
                  key={line.key}
                  dataKey={line.key}
                  name={line.name}
                  type={curve}
                  stroke={line.color}
                  fill={line.color}
                  fillOpacity={expand ? 0.4 : 0.15}
                  strokeWidth={2}
                  dot={dot(line.color)}
                  stackId={stacked ? 'stack' : undefined}
                >
                  {widget.showLabels === true && <LabelList dataKey={typeof widget.labelKey === 'string' ? widget.labelKey : line.key} position="top" offset={10} className="fill-foreground" fontSize={11} />}
                </Area>
              ),
            )}
          </Chart>
        </ChartContainer>
      </ChartFrame>
    </div>
  );
}
