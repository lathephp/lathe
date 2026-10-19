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
    <div className="flex flex-col gap-1 md:gap-1.5">
      <div className="flex min-w-0 items-center gap-2">
        {icon !== null && (
          <Suspense fallback={<span className="size-5 shrink-0" />}>
            <WidgetIcon name={icon} />
          </Suspense>
        )}
        <p className="truncate text-lg leading-6 font-[650] tabular-nums md:text-[26px] md:leading-8 md:tracking-[-0.02em]">
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
          // A phone shows the change as coloured text alone, without the badge and the previous value.
          <span
            data-change={direction}
            className={cn(
              'inline-flex shrink-0 items-center gap-0.5 font-medium tabular-nums md:h-[22px] md:rounded-md md:px-1.5',
              good === true && 'text-success-subtle-foreground md:bg-success-subtle',
              good === false && 'text-destructive-subtle-foreground md:bg-destructive-subtle',
              good === null && 'text-muted-foreground md:bg-muted',
            )}
          >
            <Arrow aria-hidden="true" strokeWidth={2.5} className="size-3" />
            {formatNumber(change / 100, { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1, signDisplay: 'exceptZero' })}
          </span>
        )}
        {result.previous !== null && change !== null && (
          <span className="text-muted-foreground tabular-nums max-md:hidden">
            {result.previousLabel === null ? __('vs :value', { value: formatMetric(result.previous, result, true) }) : `${result.previousLabel}: ${formatMetric(result.previous, result, true)}`}
          </span>
        )}
        {result.previous === 0 && change === null && <span className="text-muted-foreground">{__('No earlier data to compare with.')}</span>}
      </div>
    </div>
  );
}
