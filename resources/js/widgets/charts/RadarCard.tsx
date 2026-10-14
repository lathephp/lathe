import { formatNumber, useTranslation } from '@lathephp/core';
import { ChartContainer, ChartLegend, ChartLegendContent, PolarAngleAxis, PolarGrid, Radar, RadarChart } from '@lathephp/ui/chart';
import type { WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import { seriesColor } from './palette';
import { useChartTooltip } from './tooltip';

/** RadarResult::jsonSerialize(). */
export interface RadarPayload {
  axes: { key: string; label: string }[];
  datasets: { name: string; data: number[] }[];
  dots: boolean;
  gridType: 'polygon' | 'circle';
  fillOpacity: number;
  lineOnly: boolean;
  gridNone: boolean;
  radialLines: boolean;
  gridFilled: boolean;
  showLegend: boolean;
  polarRadius?: number[];
}

const format = (value: number) => formatNumber(value);

/** Datasets drawn over a ring of axes. */
export function RadarCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as RadarPayload | null;
  const tooltip = useChartTooltip(widget, {}, format);

  if (result === null || result.axes.length === 0 || result.datasets.length === 0) {
    return <NoData />;
  }

  const data = result.axes.map((axis, index) => ({
    axis: axis.label,
    ...Object.fromEntries(result.datasets.map((dataset, set) => [`s${String(set)}`, dataset.data[index] ?? 0])),
  }));
  const rows = result.axes.map((axis, index) => [axis.label, ...result.datasets.map((dataset) => format(dataset.data[index] ?? 0))]);
  const several = result.datasets.length > 1;

  return (
    <ChartFrame name={widget.name} columns={[__('Label'), ...result.datasets.map((dataset) => dataset.name)]} rows={rows}>
      <ChartContainer className="mx-auto h-full w-full">
        <RadarChart data={data}>
          {tooltip}
          <PolarAngleAxis dataKey="axis" />
          {!result.gridNone && (
            <PolarGrid
              gridType={result.gridType}
              radialLines={result.radialLines}
              polarRadius={result.polarRadius}
              fill={result.gridFilled ? seriesColor(0) : undefined}
              fillOpacity={result.gridFilled ? 0.2 : undefined}
            />
          )}
          {result.datasets.map((dataset, set) => (
            <Radar
              key={dataset.name}
              name={dataset.name}
              dataKey={`s${String(set)}`}
              stroke={seriesColor(set)}
              fill={seriesColor(set)}
              fillOpacity={result.lineOnly || (several && set > 0) ? 0 : result.fillOpacity}
              strokeWidth={2}
              dot={result.dots ? { r: 4, fillOpacity: 1 } : false}
            />
          ))}
          {(result.showLegend || several) && <ChartLegend content={<ChartLegendContent />} />}
        </RadarChart>
      </ChartContainer>
    </ChartFrame>
  );
}
