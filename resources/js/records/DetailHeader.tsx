import { Link, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { __panel, useTranslation } from '@lathephp/core';
import { Pencil } from 'lucide-react';
import { DetailField } from '../fields/registry';
import type { DetailProps } from '../fields/types';
import { PageHeader } from '../layout/PageHeader';
import type { SharedProps } from '../types';
import { RecordActions } from './RecordActions';

// The action menu loads after the record, so a page without actions never fetches it.
const RecordActionMenu = lazy(() => import('../actions/RecordActionMenu').then((module) => ({ default: module.RecordActionMenu })));
const { RenderHook } = __panel;

/** A record's title, badge and meta line (or subtitle), with its render hooks, actions, Replicate and Edit. */
export function DetailHeader({ resource, record, actions }: Pick<DetailProps, 'resource' | 'record' | 'actions'>) {
  const { id, title, subtitle, abilities, header } = record;
  const badge = record.fields.find((field) => field.key === header?.badge);
  const meta = record.fields.find((field) => field.key === header?.meta);
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const recordPath = `${basePath}/resources/${resource.uriKey}/${String(id)}`;

  return (
    <PageHeader
      title={title}
      badge={badge === undefined ? null : <DetailField field={badge} />}
      subtitle={
        meta === undefined ? (
          subtitle === '' ? null : <p className="lathe-page-subtitle">{subtitle}</p>
        ) : (
          <p data-testid="record-meta" className="lathe-page-subtitle">
            {meta.label} <DetailField field={meta} />
          </p>
        )
      }
      actions={
        <>
          <RenderHook position="detail:header-actions" resource={resource.uriKey} record={record} />
          {actions !== undefined && actions.actions.length > 0 && (
            <Suspense fallback={null}>
              <RecordActionMenu uriKey={resource.uriKey} id={id} actions={actions.actions} />
            </Suspense>
          )}
          <RecordActions uriKey={resource.uriKey} record={record} />
          {!record.trashed && abilities.replicate ? (
            <Link href={`${recordPath}/replicate`} className="lathe-button lathe-button-outline lathe-button-size-default">
              {__('Replicate')}
            </Link>
          ) : null}
          {!record.trashed && abilities.update ? (
            <Link href={`${recordPath}/edit`} aria-keyshortcuts="e" className="lathe-button lathe-button-default lathe-button-size-default ps-2.5">
              <Pencil aria-hidden="true" className="size-4" />
              {__('Edit')}
            </Link>
          ) : null}
        </>
      }
    />
  );
}
