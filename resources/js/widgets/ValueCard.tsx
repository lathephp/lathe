import { formatNumber, toast, useTranslation } from '@lathephp/core';
import { ArrowDownRight, ArrowRight, ArrowUpRight, Copy } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { cn } from '../lib/cn';
import { useAnimatedNumber } from './animate';
import { formatMetric } from './number';
import type { ValueResult, WidgetRendererProps } from './types';

const WidgetIcon = lazy(() => import('./WidgetIcon'));

/** One number, the number before it and the change between them. */
export function ValueCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as ValueResult | null;
  const number = result?.value ?? null;
  const shown = useAnimatedNumber(number);
  const icon = typeof widget.icon === 'string' ? widget.icon : null;

  if (result === null || (number === 0 && !result.allowZeroResult)) {
    return <p className="text-sm font-medium text-muted-foreground">{__('No Data')}</p>;
  }

  // A calculate() that returns a bare number sends no change at all.
  const change = typeof result.change === 'number' && Number.isFinite(result.change) ? result.change : null;
  const direction = change === null ? null : change > 0 ? 'up' : change < 0 ? 'down' : 'flat';
  const Arrow = direction === 'up' ? ArrowUpRight : direction === 'down' ? ArrowDownRight : ArrowRight;

  return (
    <div className="space-y-1">
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-center gap-2">
          {icon !== null && (
            <Suspense fallback={<span className="size-5 shrink-0" />}>
              <WidgetIcon name={icon} />
            </Suspense>
          )}
          <p className="truncate text-3xl font-semibold tracking-tight tabular-nums">{formatMetric(shown, result)}</p>
          {result.copyable && (
            <button
              type="button"
              aria-label={__('Copy')}
              className="rounded p-1 text-muted-foreground hover:text-foreground"
              onClick={() => {
                void navigator.clipboard.writeText(formatMetric(number, result)).then(() => {
                  toast.success(__('Copied to clipboard'));
                });
              }}
            >
              <Copy aria-hidden="true" className="size-3.5" />
            </button>
          )}
        </div>
        {change !== null && (
          <span
            data-change={direction}
            className={cn(
              'flex shrink-0 items-center gap-1 rounded-md px-2 py-1 text-xs font-medium tabular-nums',
              direction === 'up' && 'bg-success text-success-foreground',
              direction === 'down' && 'bg-destructive text-destructive-foreground',
              direction === 'flat' && 'bg-muted text-muted-foreground',
            )}
          >
            <Arrow aria-hidden="true" className="size-3.5" />
            {formatNumber(Math.abs(change) / 100, { style: 'percent', maximumFractionDigits: 2 })}
          </span>
        )}
      </div>
      {result.previous !== null && (
        <p className="text-xs text-muted-foreground">{`${result.previousLabel ?? __('Previous')}: ${formatMetric(result.previous, result)}`}</p>
      )}
    </div>
  );
}
