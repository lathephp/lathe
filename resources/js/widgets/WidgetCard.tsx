import { on, useTranslation } from '@lathephp/core';
import { type ReactNode, Suspense, useEffect, useEffectEvent, useRef, useState } from 'react';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Select } from '../components/ui/Select';
import { cn } from '../lib/cn';
import { HelpTooltip } from './HelpTooltip';
import { filterParams } from './filterParams';
import { cellClass } from './layout';
import { WidgetMeta } from './meta';
import { widgetRenderers } from './registry';
import type { WidgetDescriptor } from './types';
import { useWidgetValue } from './useWidgetValue';
import { WidgetMenu } from './WidgetMenu';

/** The skeleton stays this long at least, so a fast answer does not flash. */
const skeletonFloor = 300;

export interface WidgetCardProps {
  widget: WidgetDescriptor;
  /** The card's value endpoint. */
  url: string;
  /** Sent with every value request, such as resourceId on a detail page. */
  params?: Record<string, string>;
  /** Hands the grid a function that refetches the card, for the Refresh button. */
  register?: (reload: () => void) => () => void;
  /** The range the host picked for every card, as a dashboard's header does; the card then has no picker of its own. */
  range?: string;
  /** Gives the card its ⋯ menu, as a dashboard does. */
  menu?: boolean;
  /** Customize mode's header for the card. The card then keeps its last value, dimmed, and does not refetch. */
  edit?: ReactNode;
}

export function WidgetCard({ widget, url, params, register, range: hostRange, menu = false, edit }: WidgetCardProps) {
  const { __ } = useTranslation();
  const Renderer = widgetRenderers[widget.component];
  const fetches = widget.requestsValue && Renderer !== undefined;
  const first = widget.ranges.find((option) => option.value === widget.defaultRange) ?? widget.ranges[0];
  const [ownRange, setRange] = useState(first === undefined ? null : String(first.value));
  const range = hostRange ?? ownRange;
  // The filters of the last filters:changed, sent with every later request and part of the cache key.
  const [filters, setFilters] = useState<[string, string][]>([]);
  const editing = edit !== undefined;
  const { value, failed, fetchNow } = useWidgetValue(url, range, filters, params, { enabled: fetches, poll: editing ? 0 : widget.refreshEvery });
  // True while a load the user asked for is out: a range change, Try again or Refresh.
  const [pending, setPending] = useState(false);
  // A value the cache already holds shows at once; only a card with nothing to show holds the skeleton.
  const [held, setHeld] = useState(fetches && value === undefined);
  const holdTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const latest = useRef(0);
  // The header's right-hand slot, where a renderer puts a line such as a trend's latest value.
  const [meta, setMeta] = useState<HTMLSpanElement | null>(null);
  // A value or progress card is one number under a muted title; any other card has a heading.
  const compact = widget.component === 'value-widget' || widget.component === 'progress-widget';
  // A table runs to the card's edges, so the card pads only its header.
  const flush = widget.component === 'table-widget';

  // Puts up the skeleton for at least the floor, then asks past the cache: a range change, Try again or Refresh.
  // Under a host range the card dims its last value instead, so it holds no skeleton.
  const load = (nextRange = range) => {
    const id = ++latest.current;
    setPending(true);

    if (hostRange === undefined) {
      setHeld(true);
      clearTimeout(holdTimer.current);
      holdTimer.current = setTimeout(() => {
        setHeld(false);
      }, skeletonFloor);
    }

    void fetchNow(nextRange, filters).then(() => {
      if (id === latest.current) {
        setPending(false);
      }
    });
  };
  // Asks past the cache and keeps the number on screen until the answer: an event.
  const quietly = useEffectEvent((nextFilters: [string, string][] = filters) => {
    void fetchNow(range, nextFilters);
  });
  const visibly = useEffectEvent(() => {
    load();
  });

  useEffect(() => {
    const timer = setTimeout(() => {
      setHeld(false);
    }, skeletonFloor);

    return () => {
      clearTimeout(timer);
      clearTimeout(holdTimer.current);
    };
  }, []);

  useEffect(() => {
    const refetch = () => {
      quietly();
    };
    const refilter = (payload: unknown) => {
      const next = filterParams(payload);
      quietly(next);
      setFilters(next);
    };
    const offs = editing
      ? []
      : [
          fetches && widget.refreshWhenActionsRun ? on('actions:ran', refetch) : null,
          fetches && widget.refreshWhenFiltersChange ? on('filters:changed', refilter) : null,
        ];

    return () => {
      offs.forEach((off) => off?.());
    };
  }, [fetches, editing, widget.refreshWhenActionsRun, widget.refreshWhenFiltersChange]);

  // The host's range changed: the mount read the first range through the cache, so only later picks refetch.
  const shownRange = useRef(hostRange);
  const repick = useEffectEvent(() => {
    if (fetches && shownRange.current !== hostRange) {
      shownRange.current = hostRange;
      load();
    }
  });

  useEffect(() => {
    repick();
  }, [hostRange]);

  useEffect(
    () =>
      fetches
        ? register?.(() => {
            visibly();
          })
        : undefined,
    [fetches, register],
  );

  const error = Renderer === undefined || (!pending && failed);
  const refreshing = !error && hostRange !== undefined && pending && value !== undefined;
  const loading = !error && !refreshing && (held || pending || (fetches && value === undefined));
  const skeleton = (
    <div role="status" aria-label={__('Loading')} className="flex flex-col gap-2.5">
      <div className="h-[26px] w-[70%] animate-pulse rounded-md bg-muted" />
      <div className="h-3.5 w-1/2 animate-pulse rounded bg-muted" />
      <div className="h-6 w-full animate-pulse rounded bg-muted" />
    </div>
  );

  return (
    // The card does not clip, so its menu can open past its edge; its body clips instead.
    <Card
      data-lathe-widget={widget.uriKey}
      aria-busy={refreshing || undefined}
      className={cn(
        'relative flex flex-col',
        editing ? 'gap-2 border-dashed border-ring px-3 pt-2.5 pb-3' : flush ? 'gap-2 pt-3 md:gap-3 md:pt-[18px]' : 'p-3 md:px-5 md:py-[18px]',
        !editing && !flush && (compact ? 'gap-1 md:gap-1.5' : 'gap-2 md:gap-3'),
        cellClass(widget),
      )}
    >
      {refreshing && <span aria-hidden="true" className="absolute start-0 top-0 h-0.5 w-[55%] rounded-ss-[inherit] bg-brand" />}
      {edit ?? (
        <div className={cn('flex min-h-[18px] items-center justify-between gap-2', flush && 'px-3 md:px-5')}>
          <div className="flex min-w-0 items-center gap-1">
            {/* A title too long for its row wraps whole, as a legend label does. */}
            <h2 className={cn('wrap-break-word', compact ? 'text-xs font-medium text-muted-foreground md:text-[13px]' : 'text-sm font-semibold')}>{widget.name}</h2>
            {widget.help !== null && <HelpTooltip text={widget.help} width={widget.helpWidth} />}
          </div>
          {refreshing ? (
            <span className="text-xs text-muted-foreground">{__('Updating…')}</span>
          ) : (
            value?.fallbackRange !== undefined && <span className="shrink-0 text-xs text-muted-foreground">{value.fallbackRange.label}</span>
          )}
          {/* Always mounted, so a renderer's portal never loses its node; hidden while the header says something else. */}
          <span
            ref={setMeta}
            className={cn(
              'ms-auto min-w-0 text-end text-xs wrap-break-word text-muted-foreground tabular-nums empty:hidden',
              (refreshing || value?.fallbackRange !== undefined) && 'hidden',
            )}
          />
          {hostRange === undefined && widget.ranges.length > 0 && (
            <Select
              aria-label={__('Range')}
              size="sm"
              value={range ?? ''}
              options={widget.ranges.map((option) => ({ value: String(option.value), label: option.label }))}
              onChange={(next) => {
                setRange(next);
                load(next);
              }}
            />
          )}
          {menu && fetches && (
            <WidgetMenu
              name={widget.name}
              link={value?.link ?? null}
              csv={widget.csv === true ? `${url}/csv${range === null ? '' : `?${new URLSearchParams({ range }).toString()}`}` : null}
              onRefresh={() => {
                load();
              }}
            />
          )}
        </div>
      )}
      <div
        inert={editing}
        className={cn('min-h-0 flex-1 overflow-hidden', flush && !editing && (error || loading) && 'px-3 pb-3 md:px-5 md:pb-[18px]', refreshing && 'opacity-60', editing && 'pointer-events-none opacity-55')}>
        {error ? (
          <div className="flex flex-col items-start gap-1.5 text-[13px]">
            <p className="flex items-center gap-1.5 font-medium text-destructive-subtle-foreground">
              {/* Drawn here: lucide's CircleAlert would split into a chunk the index's first load then fetches. */}
              <svg aria-hidden="true" viewBox="0 0 24 24" className="size-3.5 shrink-0 fill-none stroke-current stroke-2 [stroke-linecap:round]">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 8v4M12 16h.01" />
              </svg>
              {__(':title could not load', { title: widget.name })}
            </p>
            <p className="text-xs text-muted-foreground">{__('The other widgets are not affected.')}</p>
            {Renderer !== undefined && (
              <Button
                variant="outline"
                size="sm"
                className="mt-1"
                onClick={() => {
                  load();
                }}
              >
                {__('Try again')}
              </Button>
            )}
          </div>
        ) : loading ? (
          skeleton
        ) : (
          // A lazy renderer, such as a chart, keeps the skeleton up while its chunk loads.
          <Suspense fallback={skeleton}>
            <WidgetMeta value={meta}>
              <Renderer widget={widget} value={value?.value ?? null} />
            </WidgetMeta>
          </Suspense>
        )}
      </div>
    </Card>
  );
}
