import { Link } from '@inertiajs/react';
import { ApiError, request, useLathe, useTranslation } from '@lathephp/core';
import { lazy, Suspense, useEffect, useId, useState } from 'react';
import type { ActionList } from '../actions/types';
import { DetailField } from '../fields/registry';
import type { DetailFieldData } from '../fields/types';
import { TableCell } from '../table/cells';
import type { IndexProps, Row } from './types';
import { restMs } from './usePreview';

const RecordActionMenu = lazy(() => import('../actions/RecordActionMenu').then((module) => ({ default: module.RecordActionMenu })));

interface PreviewPayload {
  id: string | number;
  title: string;
  fields: DetailFieldData[];
  abilities: Partial<Record<string, boolean>>;
  actions: ActionList;
}

// The records this tab has previewed, by "resource/id@user": another user signed in in the same
// tab sees only what they may. A save to any record of a Resource drops that Resource's, since a
// change to one record can show on another.
const cache = new Map<string, PreviewPayload>();
// Saves seen per Resource: a reply that left before a save is not cached after it.
const saved = new Map<string, number>();

function written(url: string): string | null {
  // A user's own preferences change no record.
  const match = /\/resources\/([^/?#]+)(?![^/?#]|\/(?:preview-panel|columns|saved-tabs))/.exec(url);

  return match?.[1] ?? null;
}

window.addEventListener('lathe-write', (event) => {
  const uriKey = written((event as CustomEvent<string>).detail);

  if (uriKey !== null) {
    saved.set(uriKey, (saved.get(uriKey) ?? 0) + 1);
  }

  for (const key of cache.keys()) {
    if (uriKey !== null && key.startsWith(`${uriKey}/`)) {
      cache.delete(key);
    }
  }
});

export interface PreviewPanelProps {
  /** A tag's panel, opened over any page, knows its Resource's key alone. */
  resource: Pick<IndexProps['resource'], 'uriKey'> & Partial<Pick<IndexProps['resource'], 'singularLabel'>>;
  table?: Pick<IndexProps['table'], 'columns' | 'phoneCard'>;
  id: string;
  /** The row on the page, when the record is one; its card's title and badge head the panel at once. */
  row?: Row;
  onClose: () => void;
}

/**
 * The record under the cell cursor, read-only, beside the list: its card's title and badge, its
 * preview fields, its actions and Edit. 400 px wide; it lies over the table below 1280 px and
 * fills the screen below 768 px.
 */
export function PreviewPanel({ resource, table, id, row, onClose }: PreviewPanelProps) {
  const { __ } = useTranslation();
  const { apiPath, basePath, auth } = useLathe();
  const titleId = useId();
  // The reply for the record the panel last asked for.
  const [reply, setReply] = useState<{ id: string; saves: number; data: PreviewPayload | null; failed: number | null } | null>(null);
  // Bumped by a save to this Resource, which asks for the record again; the old reply is dropped meanwhile.
  const [saves, setSaves] = useState(0);
  const key = `${resource.uriKey}/${id}`;
  const cached = `${key}@${auth?.user?.email ?? ''}`;
  const current = reply?.id === id && reply.saves === saves ? reply : null;
  const data = cache.get(cached) ?? current?.data ?? null;
  const failed = current?.failed ?? null;
  const recordPath = `${basePath}/resources/${resource.uriKey}/${id}`;

  useEffect(() => {
    const listener = (event: Event) => {
      if (written((event as CustomEvent<string>).detail) === resource.uriKey) {
        setSaves((count) => count + 1);
      }
    };
    window.addEventListener('lathe-write', listener);

    return () => {
      window.removeEventListener('lathe-write', listener);
    };
  }, [resource.uriKey]);

  // The body asks once the cursor rests on the row, so holding Down sends one request.
  useEffect(() => {
    if (cache.has(cached)) {
      return;
    }

    const abort = new AbortController();
    const timer = window.setTimeout(() => {
      const before = saved.get(resource.uriKey) ?? 0;

      request<PreviewPayload>(`${apiPath}/resources/${key}/preview`, { signal: abort.signal, toastOnError: false }).then(
        (payload) => {
          // A save since this request left asks again; this answer may predate it.
          if (abort.signal.aborted || (saved.get(resource.uriKey) ?? 0) !== before) {
            return;
          }

          if (payload !== null) {
            cache.set(cached, payload);
          }

          setReply({ id, saves, data: payload, failed: null });
        },
        (failure: unknown) => {
          if (!abort.signal.aborted) {
            setReply({ id, saves, data: null, failed: failure instanceof ApiError ? failure.status : 0 });
          }
        },
      );
    }, restMs);

    return () => {
      window.clearTimeout(timer);
      abort.abort();
    };
  }, [apiPath, resource.uriKey, key, cached, id, saves]);

  const card = table?.phoneCard;
  const label = resource.singularLabel?.toLowerCase() ?? __('record');

  // A card part from the row's cell; a record not on the page, or a column the user hid, draws it from
  // the record's own field once the reply lands, and a hidden column's cell text stands in until then.
  function cell(key: string | null | undefined) {
    const column = table?.columns.find((candidate) => candidate.key === key);
    const value = key === null || key === undefined ? undefined : row?.cells[key];
    const field = data?.fields.find((candidate) => candidate.key === key);

    return column !== undefined && value !== undefined ? <TableCell column={column} cell={value} /> : field !== undefined ? <DetailField field={field} /> : (value?.display ?? null);
  }

  const title = row === undefined ? data?.title : card?.title == null ? row.title : (cell(card.title) ?? row.title);

  return (
    <aside aria-labelledby={titleId} data-lathe-preview="" className="lathe-preview">
      <header className="lathe-preview-header">
        <div className="min-w-0">
          <h2 id={titleId} className="truncate text-lg font-semibold">
            {title ?? __('Loading…')}
          </h2>
          {cell(card?.badge)}
        </div>
        <div className="flex shrink-0 items-center gap-1">
          <Link href={recordPath} className="lathe-button lathe-button-outline lathe-button-size-sm">
            {__('Open full page')}
          </Link>
          <button type="button" className="lathe-button lathe-button-ghost lathe-button-size-sm" onClick={onClose}>
            {__('Close')}
          </button>
        </div>
      </header>
      {failed === 404 ? (
        <p className="p-4 text-sm">{__('This :resource was deleted.', { resource: label })}</p>
      ) : failed !== null ? (
        <p className="p-4 text-sm text-destructive">{failed === 403 ? __('You may not view this :resource.', { resource: label }) : __('Something went wrong. Please try again.')}</p>
      ) : data === null ? (
        <div aria-busy="true" className="space-y-3 p-4">
          {[0, 1, 2, 3].map((line) => (
            <div key={line} aria-hidden="true" className="h-4 rounded bg-muted" />
          ))}
        </div>
      ) : (
        <>
          <div className="flex flex-wrap items-center gap-2 px-4 pt-3 empty:hidden">
            {data.actions.actions.length > 0 && (
              <Suspense fallback={null}>
                <RecordActionMenu uriKey={resource.uriKey} id={data.id} actions={data.actions.actions} />
              </Suspense>
            )}
            {data.abilities.update === true && (
              <Link href={`${recordPath}/edit`} className="lathe-button lathe-button-default lathe-button-size-sm">
                {__('Edit')}
              </Link>
            )}
          </div>
          <dl className="divide-y divide-border px-4 text-sm">
            {data.fields.map((field) => (
              <div key={field.key} className="grid gap-1 py-2.5">
                <dt className="text-muted-foreground">{field.label}</dt>
                <dd className="min-w-0 break-words">
                  <DetailField field={field} />
                </dd>
              </div>
            ))}
          </dl>
        </>
      )}
    </aside>
  );
}
