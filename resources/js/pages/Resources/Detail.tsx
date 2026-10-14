import { Head, router, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { useShortcut, useTranslation } from '@lathephp/core';
import { DetailField } from '../../fields/registry';
import type { DetailFieldData, DetailProps } from '../../fields/types';
import { cn } from '../../lib/cn';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { DetailHeader } from '../../records/DetailHeader';
import { RecordLayout } from '../../records/RecordLayout';
import type { SharedProps } from '../../types';
import type { WidgetDescriptor } from '../../widgets/types';

// A record without resource plugins never fetches the block that draws them.
const ResourcePlugins = lazy(() => import('../../records/ResourcePlugins').then((module) => ({ default: module.ResourcePlugins })));
// The cards load only when the page lists one.
const ResourceWidgets = lazy(() => import('../../widgets/ResourceWidgets').then((module) => ({ default: module.ResourceWidgets })));

/** One record: its fields in the Resource's panels, grids and tabs. */
export default function Detail({ resource, record, breadcrumbs, actions, widgets = [] }: DetailProps & { widgets?: WidgetDescriptor[] }) {
  const { id, title, fields, layout, abilities } = record;
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const byKey = new Map(fields.map((field) => [field.key, field]));
  const recordPath = `${basePath}/resources/${resource.uriKey}/${String(id)}`;

  useShortcut(
    'e',
    () => {
      router.visit(`${recordPath}/edit`);
    },
    abilities.update && !record.trashed,
  );

  return (
    <div className="space-y-6">
      <Head title={title} />
      <Breadcrumbs items={breadcrumbs} />
      <DetailHeader resource={resource} record={record} actions={actions} />
      {record.trashed ? (
        <div role="status" data-lathe-banner className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
          {__('Soft Deleted')}
        </div>
      ) : null}
      {widgets.length > 0 && (
        <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
          <ResourceWidgets widgets={widgets} path={`resources/${resource.uriKey}`} resourceId={String(id)} />
        </Suspense>
      )}
      <RecordLayout
        detail
        parent={{ uriKey: resource.uriKey, id }}
        blocks={layout}
        detailFields={byKey}
        renderField={(key) => {
          const field = byKey.get(key);

          return field === undefined ? null : <DetailRow field={field} />;
        }}
      />
      {record.resourcePlugins !== undefined && record.resourcePlugins.length > 0 ? (
        <Suspense fallback={null}>
          <ResourcePlugins resourceName={resource.uriKey} resourceId={id} plugins={record.resourcePlugins} />
        </Suspense>
      ) : null}
    </div>
  );
}

function DetailRow({ field }: { field: DetailFieldData }) {
  return (
    <div className={cn('gap-2 px-4 py-3 text-sm', field.stacked ? 'flex flex-col' : 'grid md:grid-cols-[minmax(0,1fr)_minmax(0,3fr)]')}>
      {field.compact ? null : <div className="font-medium text-muted-foreground">{field.label}</div>}
      <div className={cn('min-w-0 break-words', field.textAlign === 'right' && 'text-end', field.textAlign === 'center' && 'text-center')}>
        <DetailField field={field} />
      </div>
    </div>
  );
}
