import { type PageProps, useTranslation } from '@lathephp/core';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@lathephp/ui';

/** A standalone page for whoever may view system status: what the panel hands a page component. */
export default function SystemStatus({ pageKey, basePath, path }: PageProps) {
  const { __ } = useTranslation();
  const rows: [string, string][] = [
    [__('Page key'), pageKey],
    [__('Base path'), basePath],
    [__('Sub-path'), path === undefined || path === '' ? '—' : path],
  ];

  return (
    <section className="grid gap-6">
      <h1 className="text-2xl font-semibold">{__('System Status')}</h1>
      <Card>
        <CardHeader>
          <CardTitle>{__('Page')}</CardTitle>
          <CardDescription>{__('A page registered with page() and rendered by the panel.')}</CardDescription>
        </CardHeader>
        <CardContent>
          <dl className="divide-y text-sm">
            {rows.map(([label, value]) => (
              <div key={label} className="flex items-center justify-between py-2">
                <dt className="text-muted-foreground">{label}</dt>
                <dd className="font-mono">{value}</dd>
              </div>
            ))}
          </dl>
        </CardContent>
      </Card>
    </section>
  );
}
