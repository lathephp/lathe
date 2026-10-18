import { on, request, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useEffectEvent, useRef, useState } from 'react';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { Select } from '../components/ui/Select';
import { cn } from '../lib/cn';
import { HelpTooltip } from './HelpTooltip';
import { filterParams } from './filterParams';
import { cellClass } from './layout';
import { widgetRenderers } from './registry';
import type { WidgetDescriptor, WidgetValue } from './types';

/** The skeleton stays this long at least, so a fast answer does not flash. */
const skeletonFloor = 300;

/** A dashboard card's deferred prop: the value once it lands, or rescued when the server failed it. */
export interface DeferredValue {
  value: WidgetValue | undefined;
  rescued: boolean;
}

export interface WidgetCardProps {
  widget: WidgetDescriptor;
  /** The card's value endpoint. */
  url: string;
  /** Sent with every value request, such as resourceId on a detail page. */
  params?: Record<string, string>;
  /** Given on a dashboard, where the first value comes as a deferred prop; otherwise the card fetches it. */
  deferred?: DeferredValue;
  /** Hands the grid a function that refetches the card, for the Refresh button. */
  register?: (reload: () => void) => () => void;
  /** The range the host picked for every card, as a dashboard's header does; the card then has no picker of its own. */
  range?: string;
}

export function WidgetCard({ widget, url, params, deferred, register, range: hostRange }: WidgetCardProps) {
  const { __ } = useTranslation();
  const Renderer = widgetRenderers[widget.component];
  const fetches = widget.requestsValue && Renderer !== undefined;
  const first = widget.ranges.find((option) => option.value === widget.defaultRange) ?? widget.ranges[0];
  const [ownRange, setRange] = useState(first === undefined ? null : String(first.value));
  const range = hostRange ?? ownRange;
  const [fetched, setFetched] = useState<WidgetValue | undefined>(undefined);
  const [failed, setFailed] = useState(false);
  const [pending, setPending] = useState(fetches && deferred === undefined);
  const [held, setHeld] = useState(fetches);
  const holdTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const latest = useRef(0);
  // The filters of the last filters:changed, sent with every later request.
  const filters = useRef<[string, string][]>([]);

  // Asks for the value and keeps the number on screen until the answer: a poll or an event.
  const send = (nextRange: string | null) => {
    const id = ++latest.current;
    const query = new URLSearchParams([...(nextRange === null ? [] : [['range', nextRange]]), ...filters.current, ...Object.entries(params ?? {})]).toString();

    request<WidgetValue>(query === '' ? url : `${url}?${query}`).then(
      (data) => {
        if (id === latest.current) {
          setFetched(data ?? undefined);
          setFailed(false);
          setPending(false);
        }
      },
      () => {
        if (id === latest.current) {
          setFailed(true);
          setPending(false);
        }
      },
    );
  };
  // Puts up the skeleton for at least the floor, then asks: a range change, Try again or Refresh.
  // Under a host range the card dims its last value instead, so it holds no skeleton.
  const load = (nextRange = range) => {
    setPending(true);

    if (hostRange === undefined) {
      setHeld(true);
      clearTimeout(holdTimer.current);
      holdTimer.current = setTimeout(() => {
        setHeld(false);
      }, skeletonFloor);
    }

    send(nextRange);
  };
  const quietly = useEffectEvent(() => {
    send(range);
  });
  const visibly = useEffectEvent(() => {
    load();
  });
  // A dashboard card gets its first value as a deferred prop; any other card fetches it once, on mount.
  const loadFirst = useEffectEvent(() => {
    if (fetches && deferred === undefined) {
      send(range);
    }
  });

  useEffect(() => {
    loadFirst();

    const timer = setTimeout(() => {
      setHeld(false);
    }, skeletonFloor);

    return () => {
      clearTimeout(timer);
      clearTimeout(holdTimer.current);
    };
  }, []);

  useEffect(() => {
    if (!fetches || widget.refreshEvery <= 0) {
      return;
    }

    const interval = setInterval(() => {
      quietly();
    }, widget.refreshEvery * 1000);

    return () => {
      clearInterval(interval);
    };
  }, [fetches, widget.refreshEvery]);

  useEffect(() => {
    const refetch = () => {
      quietly();
    };
    const refilter = (payload: unknown) => {
      filters.current = filterParams(payload);
      quietly();
    };
    const offs = [
      fetches && widget.refreshWhenActionsRun ? on('actions:ran', refetch) : null,
      fetches && widget.refreshWhenFiltersChange ? on('filters:changed', refilter) : null,
    ];

    return () => {
      offs.forEach((off) => off?.());
    };
  }, [fetches, widget.refreshWhenActionsRun, widget.refreshWhenFiltersChange]);

  // The host's range changed: the first value already came with the page, so only later picks refetch.
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

  const value = fetched ?? deferred?.value;
  const error = Renderer === undefined || (!pending && (failed || (fetched === undefined && deferred?.rescued === true)));
  const refreshing = !error && hostRange !== undefined && pending && value !== undefined;
  const loading = !error && !refreshing && (held || pending || (fetches && value === undefined));
  const skeleton = (
    <div role="status" aria-label={__('Loading')} className="space-y-2">
      <div className="h-8 w-24 animate-pulse rounded bg-muted" />
      <div className="h-3 w-16 animate-pulse rounded bg-muted" />
    </div>
  );

  return (
    <Card data-lathe-widget={widget.uriKey} aria-busy={refreshing || undefined} className={cn('relative flex flex-col gap-2 overflow-hidden p-4', cellClass(widget))}>
      {refreshing && <span aria-hidden="true" className="absolute inset-x-0 top-0 h-0.5 bg-brand" />}
      <div className="flex items-center justify-between gap-2">
        <div className="flex min-w-0 items-center gap-1">
          <h2 className="truncate text-sm font-medium text-muted-foreground">{widget.name}</h2>
          {widget.help !== null && <HelpTooltip text={widget.help} width={widget.helpWidth} />}
        </div>
        {refreshing ? (
          <span className="text-xs text-muted-foreground">{__('Updating…')}</span>
        ) : (
          value?.fallbackRange !== undefined && <span className="shrink-0 text-xs text-muted-foreground">{value.fallbackRange.label}</span>
        )}
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
      </div>
      <div className={cn('min-h-0 flex-1', refreshing && 'opacity-60')}>
        {error ? (
          <div className="flex flex-col items-start gap-1 text-sm">
            <p className="font-medium text-destructive-subtle-foreground">{__(':title could not load', { title: widget.name })}</p>
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
            <Renderer widget={widget} value={value?.value ?? null} />
          </Suspense>
        )}
      </div>
    </Card>
  );
}
