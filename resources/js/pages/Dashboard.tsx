import { useTranslation } from '@lathephp/core';
import { Card } from '@lathephp/ui';

interface DashboardProps {
  dashboard: { uriKey: string; title: string };
  widgets: unknown[];
}

export default function Dashboard({ dashboard, widgets }: DashboardProps) {
  const { __ } = useTranslation();

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">{dashboard.title}</h1>
      {widgets.length === 0 && (
        <Card className="p-12 text-center text-sm text-muted-foreground">
          <p>{__('No widgets yet')}</p>
        </Card>
      )}
    </div>
  );
}
