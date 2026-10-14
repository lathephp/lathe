import { config, request, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useState } from 'react';

interface Summary {
  orders: number;
  openOrders: number;
  customers: number;
  products: number;
}

/** The InsightsPlugin page: what its serving() provided, and the counts from its summary endpoint. */
export default function Overview() {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [summary, setSummary] = useState<Summary | null>(null);

  useEffect(() => {
    void request<Summary>(`${apiPath}/plugins/insights/summary`).then((next) => setSummary(next ?? null));
  }, [apiPath]);

  const rows: [string, string][] = [
    [__('Viewer'), String(config('insightsViewer') ?? '')],
    [__('Server time'), String(config('insightsServerTime') ?? '')],
    [__('Orders'), String(summary?.orders ?? '')],
    [__('Open orders'), String(summary?.openOrders ?? '')],
    [__('Customers'), String(summary?.customers ?? '')],
    [__('Products'), String(summary?.products ?? '')],
  ];

  return (
    <section>
      <h1 className="text-2xl font-semibold">{__('Overview')}</h1>
      <dl className="mt-4 grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
        {rows.map(([label, value]) => (
          <div key={label} className="contents">
            <dt className="text-muted-foreground">{label}</dt>
            <dd>{value}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}
