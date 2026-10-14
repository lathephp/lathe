import type { ComponentProps, ReactNode } from 'react';
import { Legend, ResponsiveContainer, Tooltip, type LegendPayload, type TooltipPayloadEntry } from 'recharts';

export {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  Label,
  LabelList,
  Line,
  LineChart,
  Pie,
  PieChart,
  PolarAngleAxis,
  PolarGrid,
  PolarRadiusAxis,
  Radar,
  RadarChart,
  RadialBar,
  RadialBarChart,
  Rectangle,
  ReferenceLine,
  Sector,
  XAxis,
  YAxis,
} from 'recharts';
export type { BarShapeProps, PieLabelRenderProps, PieSectorShapeProps } from 'recharts';

const join = (...classes: (string | false | undefined)[]) => classes.filter(Boolean).join(' ');

// Recharts draws its grid, cursor and tick text in fixed greys; these map them onto the theme tokens.
const chartClass =
  'flex justify-center text-xs [&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground [&_.recharts-cartesian-grid_line[stroke=\'#ccc\']]:stroke-border/50 [&_.recharts-curve.recharts-tooltip-cursor]:stroke-border [&_.recharts-dot[stroke=\'#fff\']]:stroke-transparent [&_.recharts-layer]:outline-hidden [&_.recharts-polar-grid_[stroke=\'#ccc\']]:stroke-border [&_.recharts-radial-bar-background-sector]:fill-muted [&_.recharts-rectangle.recharts-tooltip-cursor]:fill-muted [&_.recharts-reference-line_[stroke=\'#ccc\']]:stroke-border [&_.recharts-sector]:outline-hidden [&_.recharts-sector[stroke=\'#fff\']]:stroke-transparent [&_.recharts-surface]:outline-hidden';

export type ChartContainerProps = ComponentProps<'div'> & {
  children: ComponentProps<typeof ResponsiveContainer>['children'];
};

/** A chart that fills its box and takes its colours from the theme. */
export function ChartContainer({ className, children, ...props }: ChartContainerProps) {
  return (
    <div data-slot="chart" className={join('cn-chart', chartClass, className)} {...props}>
      <ResponsiveContainer initialDimension={{ width: 320, height: 200 }}>{children}</ResponsiveContainer>
    </div>
  );
}

export const ChartTooltip = Tooltip;

export interface ChartTooltipContentProps {
  /** Given by Recharts. */
  active?: boolean;
  payload?: readonly TooltipPayloadEntry[];
  label?: ReactNode;
  /** Replaces the header text. */
  heading?: string;
  indicator?: 'dot' | 'line' | 'dashed';
  hideLabel?: boolean;
  hideIndicator?: boolean;
  valuePrefix?: string;
  valueSuffix?: string;
  /** Adds a row with the sum of the values, for stacked charts. */
  showTotal?: boolean;
  totalLabel?: string;
  formatValue?: (value: number) => string;
}

/** The box a chart shows on hover: a header and one row per series. */
export function ChartTooltipContent({
  active,
  payload,
  label,
  heading,
  indicator = 'dot',
  hideLabel = false,
  hideIndicator = false,
  valuePrefix,
  valueSuffix,
  showTotal = false,
  totalLabel = 'Total',
  formatValue = (value) => value.toLocaleString(),
}: ChartTooltipContentProps) {
  if (active !== true || payload === undefined || payload.length === 0) {
    return null;
  }

  const items = payload.filter((item) => item.type !== 'none');
  const header = heading ?? label;
  const show = (value: unknown) => (
    <span className="font-mono font-medium text-foreground tabular-nums">
      {valuePrefix}
      {typeof value === 'number' ? formatValue(value) : String(value)}
      {valueSuffix}
    </span>
  );
  const total = items.reduce((sum, item) => sum + (typeof item.value === 'number' ? item.value : 0), 0);

  return (
    <div className="cn-chart-tooltip grid min-w-32 items-start gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1.5 text-xs shadow-xl">
      {!hideLabel && header !== undefined && header !== null && header !== '' && <div className="font-medium">{header}</div>}
      <div className="grid gap-1.5">
        {items.map((item, index) => {
          const color = (item.payload as { fill?: string } | undefined)?.fill ?? item.color;

          return (
            <div key={index} className={join('flex w-full items-stretch gap-2', indicator === 'dot' && 'items-center')}>
              {!hideIndicator && (
                <div
                  data-indicator={indicator}
                  className={join(
                    'shrink-0 rounded-[2px]',
                    indicator === 'dot' && 'size-2.5',
                    indicator === 'line' && 'w-1',
                    indicator === 'dashed' && 'w-0 border-[1.5px] border-dashed bg-transparent',
                  )}
                  style={{ backgroundColor: indicator === 'dashed' ? undefined : color, borderColor: color }}
                />
              )}
              <div className="flex flex-1 items-center justify-between gap-2 leading-none">
                <span className="text-muted-foreground">{item.name}</span>
                {item.value !== undefined && show(item.value)}
              </div>
            </div>
          );
        })}
        {showTotal && (
          <div className="flex items-center justify-between gap-2 border-t border-border pt-1.5 font-medium">
            {totalLabel}
            {show(total)}
          </div>
        )}
      </div>
    </div>
  );
}

export const ChartLegend = Legend;

/** One swatch and name per series, under the chart. */
export function ChartLegendContent({ payload }: { payload?: readonly LegendPayload[] }) {
  if (payload === undefined || payload.length === 0) {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 pt-3">
      {payload
        .filter((item) => item.type !== 'none')
        .map((item, index) => (
          <div key={index} className="flex items-center gap-1.5">
            <div className="size-2 shrink-0 rounded-[2px]" style={{ backgroundColor: item.color }} />
            {item.value}
          </div>
        ))}
    </div>
  );
}
