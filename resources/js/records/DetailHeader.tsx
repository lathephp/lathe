import { Link, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { __panel, shortcutsEnabled, useTranslation } from '@lathephp/core';
import type { DetailProps } from '../fields/types';
import type { SharedProps } from '../types';
import { RecordActions } from './RecordActions';

// The action menu loads after the record, so a page without actions never fetches it.
const RecordActionMenu = lazy(() => import('../actions/RecordActionMenu').then((module) => ({ default: module.RecordActionMenu })));
const { RenderHook } = __panel;

/** A record's title and subtitle, with its render hooks, actions, Replicate and Edit. */
export function DetailHeader({ resource, record, actions }: Pick<DetailProps, 'resource' | 'record' | 'actions'>) {
  const { id, title, subtitle, abilities } = record;
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const recordPath = `${basePath}/resources/${resource.uriKey}/${String(id)}`;

  return (
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
  );
}
