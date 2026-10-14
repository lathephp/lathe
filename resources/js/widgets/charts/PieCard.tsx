import { useTranslation } from '@lathephp/core';
import { ChartContainer, ChartLegend, ChartLegendContent, Label, LabelList, Pie, PieChart, Sector, type PieLabelRenderProps, type PieSectorShapeProps } from '@lathephp/ui/chart';
import { chartFormatter } from '../number';
import type { NumberDisplay, WidgetRendererProps } from '../types';
import { ChartFrame, NoData } from './ChartFrame';
import { useChartTooltip } from './tooltip';

export interface Segment {
  label: string;
  value: number;
  percentage: number;
  color: string;
}

/** PieResult::jsonSerialize(). */
export interface PiePayload extends Partial<NumberDisplay> {
  value: Segment[];
  innerRadius: number;
  showLabels?: boolean;
  series?: { name: string; segments: Segment[] }[];
}

const outer = 80;

/** The sectors of one ring, coloured by the server. */
const sectors = (segments: Segment[]) => segments.map((segment) => ({ name: segment.label, value: segment.value, fill: segment.color }));

/** Segments as a pie or a donut, or one ring per series. */
export function PieCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as PiePayload | null;
  const format = chartFormatter(result);
  const tooltip = useChartTooltip(widget, { hideLabel: true }, format);
  const rings = result?.series ?? [];
  const segments = result?.value ?? [];

  if (result === null || (segments.length === 0 && rings.length === 0)) {
    return <NoData />;
  }

  const active = widget.activeDonut as { strokeWidth: number; activeIndex: number } | undefined;
  const inner = result.innerRadius > 0 ? outer * result.innerRadius : 0;
  const total = segments.reduce((sum, segment) => sum + segment.value, 0);
  const labelMode = widget.labelMode as 'inline' | 'custom' | 'list' | undefined;
  const stroke = widget.separatorNone === true ? { stroke: 'none' } : { stroke: 'var(--background)' };
  // The active donut keeps one sector drawn larger, the first unless the server says otherwise.
  const shape = (props: PieSectorShapeProps) =>
    active !== undefined && props.index === active.activeIndex ? <Sector {...props} outerRadius={props.outerRadius + 6} /> : <Sector {...props} />;
  const label =
    labelMode === 'inline'
      ? ({ name }: PieLabelRenderProps) => String(name)
      : labelMode === 'custom'
        ? ({ name, value: number }: PieLabelRenderProps) => `${String(name)}: ${typeof number === 'number' ? format(number) : String(number)}`
        : result.showLabels === true;
  const rows = (rings.length > 0 ? rings.flatMap((ring) => ring.segments.map((segment) => ({ ...segment, label: `${ring.name}: ${segment.label}` }))) : segments).map(
    (segment) => [segment.label, format(segment.value), `${String(segment.percentage)}%`],
  );

  return (
    <ChartFrame name={widget.name} columns={[__('Label'), __('Value'), __('Percentage')]} rows={rows}>
      <ChartContainer className="mx-auto h-full w-full">
        <PieChart>
          {tooltip}
          {rings.length > 0 ? (
            rings.map((ring, index) => (
              <Pie key={ring.name} data={sectors(ring.segments)} dataKey="value" nameKey="name" innerRadius={index === 0 ? 0 : 30 + index * 25} outerRadius={25 + index * 25 + (index === 0 ? 0 : 3)} {...stroke} />
            ))
          ) : (
            <Pie
              data={sectors(segments)}
              dataKey="value"
              nameKey="name"
              innerRadius={inner}
              outerRadius={outer}
              strokeWidth={active === undefined ? 1 : active.strokeWidth}
              shape={shape}
              label={label}
              labelLine={labelMode === 'inline' || result.showLabels === true}
              {...stroke}
            >
              {labelMode === 'list' && <LabelList dataKey="name" className="fill-background" stroke="none" fontSize={10} />}
              {widget.showTotal === true && inner > 0 && (
                <Label
                  content={({ viewBox }) => {
                    if (viewBox === undefined || !('cx' in viewBox) || !('cy' in viewBox)) {
                      return null;
                    }

                    const { cx } = viewBox;
                    const { cy } = viewBox;

                    return (
                      <text x={cx} y={cy} textAnchor="middle" dominantBaseline="middle">
                        <tspan x={cx} y={cy - 4} className="fill-foreground text-2xl font-bold">
                          {format(total)}
                        </tspan>
                        <tspan x={cx} y={cy + 16} className="fill-muted-foreground text-[10px]">
                          {__('Total')}
                        </tspan>
                      </text>
                    );
                  }}
                />
              )}
            </Pie>
          )}
          <ChartLegend content={<ChartLegendContent />} />
        </PieChart>
      </ChartContainer>
    </ChartFrame>
  );
}
