import { Head, usePage } from '@inertiajs/react';
import { useLathe, useTranslation } from '@lathephp/core';
import { AfterDelay, skeletonBar } from '../lib/instant';
import { Card } from '../components/ui/Card';
import type { WidgetDescriptor, WidgetValue } from '../widgets/types';
import { WidgetGrid } from '../widgets/WidgetGrid';

export interface DashboardPayload {
  uriKey: string;
  name: string;
  description: string | null;
  icon: string | null;
  showRefreshButton: boolean;
}

// A shell command, so it is never translated.
const widgetCommand = 'php artisan lathe:widget TotalUsers --type=value';

interface DashboardProps {
  dashboard: DashboardPayload;
  widgets: WidgetDescriptor[];
}

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Dashboard(props: DashboardProps | { title: string; dashboard?: undefined }) {
  return props.dashboard === undefined ? <DashboardSkeleton title={props.title} /> : <DashboardPage {...props} />;
}

function DashboardPage({ dashboard, widgets }: DashboardProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const page = usePage();
  // The server leaves rescuedProps out when no deferred prop failed, whatever the type says.
  const rescued = (page.rescuedProps as string[] | undefined) ?? [];

  return (
    <div className="space-y-6">
      <Head title={dashboard.name} />
      <div>
        <h1 className="text-2xl font-semibold">{dashboard.name}</h1>
        {dashboard.description !== null && <p className="text-sm text-muted-foreground">{dashboard.description}</p>}
      </div>
      {widgets.length > 0 && (
        <WidgetGrid
          widgets={widgets}
          valueUrl={`${apiPath}/dashboards/${dashboard.uriKey}/widgets`}
          showRefreshButton={dashboard.showRefreshButton}
          // Each card's value is its own deferred prop, so the cards fill in one by one.
          deferred={(uriKey) => ({
            value: page.props[`widgetValue:${uriKey}`] as WidgetValue | undefined,
            rescued: rescued.includes(`widgetValue:${uriKey}`),
          })}
        />
      )}
      {widgets.length === 0 && (
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
