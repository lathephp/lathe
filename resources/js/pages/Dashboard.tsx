import { Head } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Card } from '@lathephp/ui';

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
  widgets: unknown[];
}

export default function Dashboard({ dashboard, widgets }: DashboardProps) {
  const { __ } = useTranslation();

  return (
    <div className="space-y-6">
      <Head title={dashboard.name} />
      <div>
        <h1 className="text-2xl font-semibold">{dashboard.name}</h1>
        {dashboard.description !== null && <p className="text-sm text-muted-foreground">{dashboard.description}</p>}
      </div>
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
