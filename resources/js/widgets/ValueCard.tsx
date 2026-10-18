import { formatNumber, toast, useTranslation } from '@lathephp/core';
import { ArrowDownRight, ArrowRight, ArrowUpRight, Copy } from 'lucide-react';
import { lazy, Suspense } from 'react';
import { cn } from '../lib/cn';
import { formatMetric } from './number';
import type { ValueResult, WidgetRendererProps } from './types';

const WidgetIcon = lazy(() => import('./WidgetIcon'));

/** One number, the number before it and the change between them. */
export function ValueCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const result = value as ValueResult | null;
  const number = result?.value ?? null;
  const icon = typeof widget.icon === 'string' ? widget.icon : null;

  if (result === null || (number === 0 && !result.allowZeroResult)) {
    return <p className="text-sm font-medium text-muted-foreground">{__('No Data')}</p>;
  }

  // A calculate() that returns a bare number sends no change at all.
  const change = typeof result.change === 'number' && Number.isFinite(result.change) ? result.change : null;
  const direction = change === null ? null : change > 0 ? 'up' : change < 0 ? 'down' : 'flat';
  const Arrow = direction === 'up' ? ArrowUpRight : direction === 'down' ? ArrowDownRight : ArrowRight;

  const full = formatMetric(number, result, true);
  const short = formatMetric(number, result);
  // A rise is good unless the result says lower is better.
  const good = direction === 'flat' ? null : (direction === 'up') !== (result.lowerIsBetter === true);

  return (
    <div className="space-y-1.5">
      <div className="flex min-w-0 items-center gap-2">
        {icon !== null && (
          <Suspense fallback={<span className="size-5 shrink-0" />}>
            <WidgetIcon name={icon} />
          </Suspense>
        )}
        <p className="truncate text-[26px] leading-8 font-semibold tracking-tight tabular-nums">
          {/* Every digit on a desktop; a phone shows the short form and reads the full one aloud. */}
          {short === full ? (
            full
          ) : (
            <>
              <span className="max-md:sr-only">{full}</span>
              <span aria-hidden="true" className="md:hidden">
                {short}
              </span>
            </>
          )}
        </p>
        {result.copyable && (
          <button
            type="button"
            aria-label={__('Copy')}
            className="rounded p-1 text-muted-foreground hover:text-foreground"
            onClick={() => {
              void navigator.clipboard.writeText(full).then(() => {
                toast.success(__('Copied to clipboard'));
              });
            }}
          >
            <Copy aria-hidden="true" className="size-3.5" />
          </button>
        )}
      </div>
      <div className="flex flex-wrap items-center gap-2 text-xs">
        {change !== null && (
          <span
            data-change={direction}
            className={cn(
              'inline-flex h-[22px] shrink-0 items-center gap-1 rounded-md px-1.5 font-medium tabular-nums',
              good === true && 'bg-success-subtle text-success-subtle-foreground',
              good === false && 'bg-destructive-subtle text-destructive-subtle-foreground',
              good === null && 'bg-muted text-muted-foreground',
            )}
          >
            <Arrow aria-hidden="true" className="size-3.5" />
            {formatNumber(change / 100, { style: 'percent', maximumFractionDigits: 2, signDisplay: 'exceptZero' })}
          </span>
        )}
        {result.previous !== null && change !== null && (
          <span className="text-muted-foreground tabular-nums">
            {result.previousLabel === null ? __('vs :value', { value: formatMetric(result.previous, result, true) }) : `${result.previousLabel}: ${formatMetric(result.previous, result, true)}`}
          </span>
        )}
        {result.previous === 0 && change === null && <span className="text-muted-foreground">{__('No earlier data to compare with.')}</span>}
      </div>
    </div>
  );
}
