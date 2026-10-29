import { request, useLathe, useQuery } from '@lathephp/core';
import { createPortal } from 'react-dom';
import { DetailField } from '../registry';
import type { DetailFieldData } from '../types';

interface PeekPayload {
  id: string | number;
  title: string;
  subtitle: string;
  avatar: { url?: string | null; initials?: string } | null;
  fields: DetailFieldData[];
}

/** The card a peek request fills. Each record's payload stays in the query cache, so a second hover sends nothing. */
export function PeekCard({ uriKey, id, top, left }: { uriKey: string; id: string | number; top: number; left: number }) {
  const { apiPath } = useLathe();
  const { data } = useQuery({
    queryKey: ['relation-peek', uriKey, id],
    queryFn: ({ signal }) => request<PeekPayload>(`${apiPath}/resources/${uriKey}/${String(id)}/peek`, { signal }),
    staleTime: Infinity,
  });

  if (data === undefined || data === null) {
    return null;
  }

  // The card goes to document.body, fixed, because a table cell's overflow would clip it.
  return createPortal(
    <div role="tooltip" style={{ position: 'fixed', top: `${String(top)}px`, left: `${String(left)}px` }} className="z-50 w-72 max-w-[calc(100vw-1rem)] rounded-md border border-border bg-background p-3 text-foreground shadow-md">
      <div className="flex items-center gap-2">
        {typeof data.avatar?.url === 'string' ? (
          <img src={data.avatar.url} alt="" className="size-8 rounded-full object-cover" />
        ) : (
          <span className="bg-muted flex size-8 items-center justify-center rounded-full text-xs">{data.avatar?.initials ?? ''}</span>
        )}
        <div className="min-w-0">
          <p className="truncate font-medium">{data.title}</p>
          {data.subtitle !== '' && <p className="text-muted-foreground truncate text-sm">{data.subtitle}</p>}
        </div>
      </div>
      {data.fields.length > 0 && (
        <dl className="mt-2 space-y-1 text-sm">
          {data.fields.map((field) => (
            <div key={field.key} className="flex gap-2">
              <dt className="text-muted-foreground shrink-0">{field.label}</dt>
              <dd className="min-w-0 truncate">
                <DetailField field={field} />
              </dd>
            </div>
          ))}
        </dl>
      )}
    </div>,
    document.body,
  );
}
