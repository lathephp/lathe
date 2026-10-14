import { useTranslation } from '@lathephp/core';
import { ChartTooltip, ChartTooltipContent, type ChartTooltipContentProps } from '@lathephp/ui/chart';
import type { WidgetDescriptor } from '../types';

/** Widget::tooltip(), as Tooltip::jsonSerialize() sends it in the descriptor. */
export interface TooltipOptions {
  indicator?: 'dot' | 'line' | 'dashed';
  hideLabel?: boolean;
  hideIndicator?: boolean;
  label?: string;
  valuePrefix?: string;
  valueSuffix?: string;
  showTotal?: boolean;
}

/**
 * The hover tooltip of a chart card: the chart's own defaults, then the widget's tooltip
 * options over them.
 */
export function useChartTooltip(widget: WidgetDescriptor, defaults: Partial<ChartTooltipContentProps> = {}, formatValue?: (value: number) => string) {
  const { __ } = useTranslation();
  const options = (widget.tooltip ?? {}) as TooltipOptions;
  const { label, ...rest } = options;

  return (
    <ChartTooltip
      cursor={false}
      content={<ChartTooltipContent {...defaults} {...rest} heading={label} totalLabel={__('Total')} formatValue={formatValue} />}
    />
  );
}
