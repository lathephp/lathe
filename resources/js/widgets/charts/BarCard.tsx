import { formatNumber, useTranslation } from '@lathephp/core';
import { Bar, BarChart, CartesianGrid, ChartContainer, ChartLegend, ChartLegendContent, LabelList, Rectangle, ReferenceLine, XAxis, YAxis, type BarShapeProps } from '@lathephp/ui/chart';
import type { WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import { seriesColor } from './palette';
import { useChartTooltip } from './tooltip';

/** BarResult::payload(). */
export interface BarPayload {
  bars: { label: string; value: number; color: string }[];
  horizontal?: boolean;
  stacked?: boolean;
  series?: Record<string, { label: string; value: number }[]>;
  negativeColor?: string;
}

const format = (value: number) => formatNumber(value);

/** Bars, one per group, or one group of bars per label for several series. */
export function BarCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as BarPayload | null;
  const names = Object.keys(result?.series ?? {});
  const horizontal = result?.horizontal === true;
  const showLabels = widget.showLabels === true;
  const insideLabels = widget.insideLabels === true;
  const activeIndex = typeof widget.activeIndex === 'number' ? widget.activeIndex : null;
  const tooltip = useChartTooltip(widget, { hideIndicator: names.length === 0 }, format);

  if (result === null || (names.length === 0 && result.bars.length === 0)) {
    return <NoData />;
  }

  // Several series share one row per label, keyed s0, s1 and so on.
  const labels = [...new Set(names.flatMap((name) => (result.series?.[name] ?? []).map((point) => point.label)))];
  const data =
    names.length > 0
      ? labels.map((label) => ({
          label,
          ...Object.fromEntries(names.map((name, index) => [`s${String(index)}`, result.series?.[name]?.find((point) => point.label === label)?.value ?? 0])),
        }))
      : result.bars.map((bar) => ({
          label: bar.label,
          value: bar.value,
          fill: result.negativeColor !== undefined && bar.value < 0 ? result.negativeColor : bar.color,
        }));
  const rows =
    names.length > 0
      ? data.map((row) => [row.label, ...names.map((_, index) => format(Number((row as Record<string, unknown>)[`s${String(index)}`])))])
      : result.bars.map((bar) => [bar.label, format(bar.value)]);

  // Each bar takes its own colour; the highlighted one gets a dashed outline.
  const shape = (props: BarShapeProps) => {
    const fill = (props.payload as { fill?: string } | undefined)?.fill;

    return props.index === activeIndex ? (
      <Rectangle {...props} fill={fill} fillOpacity={0.8} stroke={fill} strokeDasharray={4} strokeDashoffset={4} />
    ) : (
      <Rectangle {...props} fill={fill} />
    );
  };
  const categoryAxis = horizontal ? (
    <YAxis dataKey="label" type="category" tickLine={false} axisLine={false} width={90} hide={insideLabels} />
  ) : (
    <XAxis dataKey="label" tickLine={false} axisLine={false} />
  );
  const valueAxis = horizontal ? <XAxis type="number" hide /> : <YAxis hide />;

  return (
    <ChartFrame name={widget.name} columns={[__('Label'), ...(names.length > 0 ? names : [__('Value')])]} rows={rows}>
      <ChartContainer className="h-full w-full">
        <BarChart accessibilityLayer data={data} layout={horizontal ? 'vertical' : 'horizontal'} margin={{ top: showLabels ? 20 : 4, right: horizontal ? 16 : 0, bottom: 0, left: 0 }}>
          <CartesianGrid vertical={horizontal} horizontal={!horizontal} strokeDasharray="3 3" />
          {categoryAxis}
          {valueAxis}
          {result.negativeColor !== undefined && <ReferenceLine {...(horizontal ? { x: 0 } : { y: 0 })} />}
          {tooltip}
          {names.length > 0 ? (
            <>
              <ChartLegend content={<ChartLegendContent />} />
              {names.map((name, index) => (
                <Bar
                  key={name}
                  name={name}
                  dataKey={`s${String(index)}`}
                  fill={seriesColor(index)}
                  radius={result.stacked === true ? 0 : 4}
                  stackId={result.stacked === true ? 'stack' : undefined}
                />
              ))}
            </>
          ) : (
            <Bar dataKey="value" name={__('Value')} radius={4} shape={shape}>
              {insideLabels && horizontal && <LabelList dataKey="label" position="insideLeft" offset={8} className="fill-background" fontSize={12} />}
              {(showLabels || insideLabels) && (
                <LabelList dataKey="value" position={horizontal ? 'right' : 'top'} offset={8} className="fill-foreground" fontSize={12} formatter={(label) => (typeof label === 'number' ? format(label) : String(label))} />
              )}
            </Bar>
          )}
        </BarChart>
      </ChartContainer>
    </ChartFrame>
  );
}
