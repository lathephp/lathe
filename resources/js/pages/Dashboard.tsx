import { Head, router, usePage } from '@inertiajs/react';
import { useLathe, useTranslation } from '@lathephp/core';
import { RefreshCw } from 'lucide-react';
import { type KeyboardEvent, Suspense, useEffect, useState } from 'react';
import { AfterDelay, skeletonBar } from '../lib/instant';
import { cn } from '../lib/cn';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import { AddPanel, CustomizeHeader, EditCardHeader, preloadCustomize } from '../widgets/customize/lazy';
import type { LayoutDraft, WidgetDescriptor, WidgetValue } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';

export interface DashboardPayload {
  uriKey: string;
  name: string;
  description: string | null;
  icon: string | null;
  showRefreshButton: boolean;
  /** False hides Customize. */
  customizable: boolean;
  /** Whether the user saved a layout of this dashboard. */
  customized: boolean;
  /** The header's range options; empty hides the picker. */
  ranges: RangeOption[];
  /** The picked range, from ?range=; null without options. */
  range: number | string | null;
}

interface RangeOption {
  value: number | string;
  label: string;
}

// A shell command, so it is never translated.
const widgetCommand = 'php artisan lathe:widget TotalUsers --type=value';

interface DashboardProps {
  dashboard: DashboardPayload;
  /** The cards shown, in the user's order and at their saved sizes. */
  widgets: WidgetDescriptor[];
  /** The cards the user hid. */
  hiddenWidgets: WidgetDescriptor[];
}

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Dashboard(props: DashboardProps | { title: string; dashboard?: undefined }) {
  return props.dashboard === undefined ? <DashboardSkeleton title={props.title} /> : <DashboardPage {...props} />;
}

function DashboardPage({ dashboard, widgets, hiddenWidgets }: DashboardProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const page = usePage();
  // The server leaves rescuedProps out when no deferred prop failed, whatever the type says.
  const rescued = (page.rescuedProps as string[] | undefined) ?? [];
  // The cards' reload functions, for the header's Refresh button.
  const [reloads] = useState(() => new Set<() => void>());
  const [updatedAt, setUpdatedAt] = useState(() => Date.now());
  const range = dashboard.range === null ? undefined : String(dashboard.range);
  // Customize mode's unsaved layout; null outside it. The grid shows it live.
  const [draft, setDraft] = useState<LayoutDraft | null>(null);
  const shown = draft?.shown ?? widgets;
  const change = (update: (current: LayoutDraft) => LayoutDraft) => {
    setDraft((current) => (current === null ? null : update(current)));
  };
  const customize = async () => {
    await preloadCustomize();
    setDraft({ shown: widgets, hidden: hiddenWidgets });
  };

  // The pick goes into the URL without a server visit: the cards refetch through their own endpoint.
  const pick = (value: number | string) => {
    if (String(value) === range) {
      return;
    }

    const url = new URL(page.url, window.location.origin);
    url.searchParams.set('range', String(value));
    setUpdatedAt(Date.now());
    router.replace({
      url: url.pathname + url.search,
      props: (current) => ({ ...current, dashboard: { ...(current.dashboard as DashboardPayload), range: value } }),
      preserveState: true,
      preserveScroll: true,
    });
  };
  const refresh = () => {
    setUpdatedAt(Date.now());
    reloads.forEach((reload) => {
      reload();
    });
  };

  return (
    <div className="space-y-6">
      <Head title={dashboard.name} />
      <div className="flex flex-wrap items-center gap-x-3 gap-y-3">
        <div className={cn('min-w-0', draft === null && 'flex-1')}>
          <h1 className="text-2xl font-semibold">{dashboard.name}</h1>
          {dashboard.description !== null && <p className="text-sm text-muted-foreground">{dashboard.description}</p>}
        </div>
        {draft !== null && (
          <Suspense fallback={null}>
            <CustomizeHeader
              uriKey={dashboard.uriKey}
              draft={draft}
              onDone={() => {
                setDraft(null);
              }}
            />
          </Suspense>
        )}
        {draft === null && widgets.length > 0 && (
          <>
            {dashboard.ranges.length > 0 && <RangePicker ranges={dashboard.ranges} value={range} onPick={pick} />}
            <UpdatedAgo at={updatedAt} />
            <Button variant="outline" size="icon" aria-label={__('Refresh')} onClick={refresh}>
              <RefreshCw aria-hidden="true" className="size-4" />
            </Button>
          </>
        )}
        {/* A user who hid every card still has a saved layout, and Customize to undo it. */}
        {draft === null && dashboard.customizable && (widgets.length > 0 || dashboard.customized) && (
          <Button
            variant="outline"
            onPointerEnter={() => {
              void preloadCustomize();
            }}
            onClick={() => {
              void customize();
            }}
          >
            {__('Customize')}
          </Button>
        )}
      </div>
      {(shown.length > 0 || draft !== null) && (
        // One wrapper in both modes, so the grid keeps its cards, and their values, on entering Customize.
        <div className="flex flex-col gap-5 xl:flex-row xl:items-start">
          <div className="min-w-0 flex-1">
            <WidgetGrid
              widgets={shown}
              valueUrl={`${apiPath}/dashboards/${dashboard.uriKey}/widgets`}
              range={range}
              reloads={reloads}
              menus
              // Each card's value is its own deferred prop, so the cards fill in one by one.
              deferred={(uriKey) => ({
                value: page.props[`widgetValue:${uriKey}`] as WidgetValue | undefined,
                rescued: rescued.includes(`widgetValue:${uriKey}`),
              })}
              edit={
                draft === null
                  ? undefined
                  : (widget) => (
                      <Suspense fallback={null}>
                        <EditCardHeader widget={widget} draft={draft} change={change} />
                      </Suspense>
                    )
              }
            />
          </div>
          {draft !== null && (
            <Suspense fallback={null}>
              <AddPanel name={dashboard.name} draft={draft} change={change} />
            </Suspense>
          )}
        </div>
      )}
      {shown.length === 0 && draft === null && (
        <Card className="space-y-3 p-12 text-center text-sm text-muted-foreground">
          <p>{__('No widgets yet')}</p>
          <p>
            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs text-foreground" dir="ltr">
              {widgetCommand}
            </code>
          </p>
          <p>
            <a className="underline underline-offset-4" href="https://lathephp.com/docs/widgets" rel="noopener noreferrer" target="_blank">
              {__('Read the widget docs')}
            </a>
          </p>
        </Card>
      )}
    </div>
  );
}

/** The header's segmented range control; below 768 px it takes a full-width row of its own. */
function RangePicker({ ranges, value, onPick }: { ranges: RangeOption[]; value: string | undefined; onPick: (value: number | string) => void }) {
  const { __ } = useTranslation();
  const steps: Record<string, number> = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };

  // Arrow keys pick the next option and move focus to it, as a radio group does.
  const move = (event: KeyboardEvent<HTMLButtonElement>, at: number) => {
    const step = steps[event.key];

    if (step === undefined) {
      return;
    }

    event.preventDefault();
    const next = event.currentTarget.parentElement?.children[(at + step + ranges.length) % ranges.length] as HTMLElement | undefined;
    next?.focus();
    next?.click();
  };

  return (
    <div
      role="radiogroup"
      aria-label={__('Range')}
      className="order-last grid basis-full auto-cols-fr grid-flow-col gap-0.5 rounded-lg border bg-muted p-0.5 md:order-none md:inline-grid md:basis-auto"
    >
      {ranges.map((option, at) => {
        const checked = String(option.value) === value;

        return (
          <button
            key={String(option.value)}
            type="button"
            role="radio"
            aria-checked={checked}
            tabIndex={checked ? 0 : -1}
            className={cn(
              'h-9 rounded-md px-2.5 text-sm font-medium whitespace-nowrap md:h-7',
              checked ? 'bg-card text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground',
            )}
            onClick={() => {
              onPick(option.value);
            }}
            onKeyDown={(event) => {
              move(event, at);
            }}
          >
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

/** "Updated ... ago" for the last pick or Refresh, rewritten each minute. */
function UpdatedAgo({ at }: { at: number }) {
  const { __ } = useTranslation();
  const [now, setNow] = useState(at);

  useEffect(() => {
    const timer = setInterval(() => {
      setNow(Date.now());
    }, 60_000);

    return () => {
      clearInterval(timer);
    };
  }, []);

  const minutes = Math.floor(Math.max(0, now - at) / 60_000);

  return (
    <span className="hidden text-xs text-muted-foreground md:inline">
      {minutes === 0 ? __('Updated just now') : __('Updated :count min ago', { count: minutes })}
    </span>
  );
}

/** The page before its props arrive: its title and frames where widgets go, since the widgets come with the page. */
function DashboardSkeleton({ title }: { title: string }) {
  return (
    <AfterDelay>
      <Head title={title} />
      <div aria-busy="true" className="space-y-6">
        <h1 className="text-2xl font-semibold">{title}</h1>
        <div aria-hidden="true" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {[0, 1, 2, 3].map((frame) => (
            <Card key={frame} data-skeleton-frame="" className="h-32 p-4">
              <div className={`h-3 w-24 ${skeletonBar}`} />
              <div className={`mt-4 h-8 w-32 ${skeletonBar}`} />
            </Card>
          ))}
        </div>
      </div>
    </AfterDelay>
  );
}
