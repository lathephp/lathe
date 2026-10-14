import { Head, Link, router, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { __panel, shortcutsEnabled, useShortcut, useTranslation } from '@lathephp/core';
import { DetailField } from '../../fields/registry';
import type { DetailFieldData, DetailProps } from '../../fields/types';
import { cn } from '../../lib/cn';
import { Breadcrumbs } from '../../records/Breadcrumbs';
import { RecordActions } from '../../records/RecordActions';
import { RecordLayout } from '../../records/RecordLayout';
import type { SharedProps } from '../../types';

// The action menu loads after the record, so a page without actions never fetches it.
const RecordActionMenu = lazy(() => import('../../actions/RecordActionMenu').then((module) => ({ default: module.RecordActionMenu })));
// A record without resource plugins never fetches the block that draws them.
const ResourcePlugins = lazy(() => import('../../records/ResourcePlugins').then((module) => ({ default: module.ResourcePlugins })));
const { RenderHook } = __panel;

/** One record: its fields in the Resource's panels, grids and tabs. */
export default function Detail({ resource, record, breadcrumbs, actions }: DetailProps) {
  const { id, title, subtitle, fields, layout, abilities } = record;
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
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h1 className="truncate text-xl font-semibold">{title}</h1>
          {subtitle === '' ? null : <p className="text-sm text-muted-foreground">{subtitle}</p>}
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <RenderHook position="detail:header-actions" resource={resource.uriKey} record={record} />
          {actions !== undefined && actions.actions.length > 0 && (
            <Suspense fallback={null}>
              <RecordActionMenu uriKey={resource.uriKey} id={id} actions={actions.actions} />
            </Suspense>
          )}
          <RecordActions uriKey={resource.uriKey} record={record} />
          {!record.trashed && abilities.replicate ? (
            <Link
              href={`${recordPath}/replicate`}
              className="inline-flex h-9 items-center rounded-md border border-border px-4 text-sm font-medium"
            >
              {__('Replicate')}
            </Link>
          ) : null}
          {!record.trashed && abilities.update ? (
            <Link
              href={`${recordPath}/edit`}
              aria-keyshortcuts="e"
              className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
            >
              {__('Edit')}
              {shortcutsEnabled() && (
                <kbd aria-hidden="true" className="text-xs opacity-80 max-md:hidden">{'e'.toUpperCase()}</kbd>
              )}
            </Link>
          ) : null}
        </div>
      </header>
      {record.trashed ? (
        <div role="status" data-lathe-banner className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">
          {__('Soft Deleted')}
        </div>
      ) : null}
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
