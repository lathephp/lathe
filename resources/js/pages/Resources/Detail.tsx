import { Head, router, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { useShortcut, useTranslation } from '@lathephp/core';
import { AfterDelay, skeletonBar } from '../../lib/instant';
import { Card } from '../../components/ui/Card';
import type { DetailProps } from '../../fields/types';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { DetailHeader } from '../../records/DetailHeader';
import { RecordColumns } from '../../records/RecordColumns';
import type { SharedProps } from '../../types';
import type { WidgetDescriptor } from '../../widgets/types';

// A record without resource plugins never fetches the block that draws them.
const ResourcePlugins = lazy(() => import('../../records/ResourcePlugins').then((module) => ({ default: module.ResourcePlugins })));
// The cards load only when the page lists one.
const ResourceWidgets = lazy(() => import('../../widgets/ResourceWidgets').then((module) => ({ default: module.ResourceWidgets })));

/** One record: its relations as tabs, its fields in a Details card and its related records in cards. */
type DetailPageProps = DetailProps & { widgets?: WidgetDescriptor[] };

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Detail(props: DetailPageProps | { title: string; record?: undefined }) {
  return props.record === undefined ? <RecordSkeleton title={props.title} /> : <DetailPage {...props} />;
}

function DetailPage({ resource, record, breadcrumbs, actions, widgets = [] }: DetailPageProps) {
  const { id, title, abilities } = record;
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
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
        <div role="status" data-lathe-banner className="rounded-md border border-warning bg-warning-subtle px-4 py-3 text-sm font-medium text-warning-subtle-foreground">
          {__('Soft Deleted')}
        </div>
      ) : null}
      {widgets.length > 0 && (
        <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
          <ResourceWidgets widgets={widgets} path={`resources/${resource.uriKey}`} resourceId={String(id)} />
        </Suspense>
      )}
      <RecordColumns record={record} parent={{ uriKey: resource.uriKey, id }} />
      {record.resourcePlugins !== undefined && record.resourcePlugins.length > 0 ? (
        <Suspense fallback={null}>
          <ResourcePlugins resourceName={resource.uriKey} resourceId={id} plugins={record.resourcePlugins} />
        </Suspense>
      ) : null}
    </div>
  );
}

/** The page before its props arrive: its title, then the two columns of cards the record fills. */
function RecordSkeleton({ title }: { title: string }) {
  const card = (
    <Card data-skeleton-card="" aria-hidden="true" className="space-y-3 p-4">
      <div className={`h-4 w-32 ${skeletonBar}`} />
      <div className={`h-3 w-full ${skeletonBar}`} />
      <div className={`h-3 w-2/3 ${skeletonBar}`} />
    </Card>
  );

  return (
    <AfterDelay>
      <Head title={title} />
      <div aria-busy="true" className="space-y-6">
        <div aria-hidden="true" className={`h-4 w-48 ${skeletonBar}`} />
        <div className="flex items-center gap-3">
          <h1 className="truncate text-xl font-semibold">{title}</h1>
          <div aria-hidden="true" className={`ms-auto h-9 w-40 ${skeletonBar}`} />
        </div>
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div className="min-w-0 space-y-6 lg:col-span-2">
            <div aria-hidden="true" className={`h-9 w-full ${skeletonBar}`} />
            {card}
          </div>
          <div className="min-w-0 space-y-6">
            {card}
            {card}
          </div>
        </div>
      </div>
    </AfterDelay>
  );
}
