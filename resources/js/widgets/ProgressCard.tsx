import { formatNumber, useTranslation } from '@lathephp/core';
import { cn } from '../lib/cn';
import { formatMetric } from './number';
import type { ProgressResult, WidgetRendererProps } from './types';

// Literal class names, so Tailwind finds them.
const colors: Record<ProgressResult['color'], string> = {
  primary: 'bg-primary',
  info: 'bg-info',
  destructive: 'bg-destructive',
  warning: 'bg-warning',
  success: 'bg-success',
};

/** How far a number has come towards its target: the number of its target, a bar, then the share. */
export function ProgressCard({ value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as ProgressResult;

  return (
    <div className="flex flex-col gap-1 md:gap-1.5">
      <p className="truncate text-lg leading-6 font-[650] font-stretch-[122%] tabular-nums md:text-[26px] md:leading-8 md:tracking-[-0.02em]">
        <span>{formatMetric(result.value, result)}</span>{' '}
        <span className="text-[13px] font-medium tracking-normal text-muted-foreground md:text-[15px]">{__('of :total', { total: formatMetric(result.target, result, true) })}</span>
      </p>
      <div
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={result.percentage}
        aria-label={result.caption}
        className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted md:mt-1.5 md:h-2"
      >
        <div className={cn('h-full rounded-full', colors[result.color])} style={{ width: `${String(Math.min(100, result.percentage))}%` }} />
      </div>
      <p className="text-xs text-muted-foreground tabular-nums max-md:hidden">
        <span>{formatNumber(result.percentage / 100, { style: 'percent', maximumFractionDigits: 1 })}</span> <span>{result.caption}</span>
      </p>
    </div>
  );
}
