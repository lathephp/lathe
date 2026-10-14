import { formatNumber } from '@lathephp/core';
import { cn } from '../lib/cn';
import { useAnimatedNumber } from './animate';
import { formatMetric } from './number';
import type { ProgressResult, WidgetRendererProps } from './types';

// Literal class names, so Tailwind finds them.
const colors: Record<ProgressResult['color'], string> = {
  destructive: 'bg-destructive',
  warning: 'bg-warning',
  success: 'bg-success',
};

/** How far a number has come towards its target, as a bar. */
export function ProgressCard({ value }: WidgetRendererProps) {
  const result = value as ProgressResult;
  const shown = useAnimatedNumber(result.value);

  return (
    <div className="space-y-2">
      <div className="flex items-baseline justify-between gap-3">
        <p className="truncate text-3xl font-semibold tracking-tight tabular-nums">{formatMetric(shown, result)}</p>
        <p className="text-sm font-medium tabular-nums">{formatNumber(result.percentage / 100, { style: 'percent', maximumFractionDigits: 2 })}</p>
      </div>
      <div
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={result.percentage}
        aria-label={result.caption}
        className="h-2 overflow-hidden rounded-full bg-muted"
      >
        <div className={cn('h-full rounded-full', colors[result.color])} style={{ width: `${String(Math.min(100, result.percentage))}%` }} />
      </div>
      <p className="text-xs text-muted-foreground">{result.caption}</p>
    </div>
  );
}
