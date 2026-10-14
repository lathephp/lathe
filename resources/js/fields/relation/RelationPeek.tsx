import { request, useLathe, useQuery } from '@lathephp/core';
import { useRef, useState, type ReactNode } from 'react';
import { DetailField } from '../registry';
import type { DetailFieldData } from '../types';

interface PeekPayload {
  id: string | number;
  title: string;
  subtitle: string;
  avatar: { url?: string | null; initials?: string } | null;
  fields: DetailFieldData[];
}

// Long enough that a pointer passing over a row opens nothing.
const hoverDelayMs = 300;

/** The card a peek request fills. Each record's payload stays in the query cache, so a second hover sends nothing. */
function PeekCard({ uriKey, id }: { uriKey: string; id: string | number }) {
  const { apiPath } = useLathe();
  const { data } = useQuery({
    queryKey: ['relation-peek', uriKey, id],
    queryFn: ({ signal }) => request<PeekPayload>(`${apiPath}/resources/${uriKey}/${String(id)}/peek`, { signal }),
    staleTime: Infinity,
  });

  if (data === undefined || data === null) {
    return null;
  }

  return (
    <div role="tooltip" className="bg-popover text-popover-foreground absolute top-full left-0 z-50 mt-1 w-72 rounded-md border p-3 shadow-md">
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
    </div>
  );
}

/** Wraps a related value so hovering it opens a card of the related record's peek fields. */
export function RelationPeek({ uriKey, id, children }: { uriKey: string; id: string | number; children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  return (
    <span
      className="relative inline-block"
      onMouseEnter={() => {
        timer.current = setTimeout(() => {
          setOpen(true);
        }, hoverDelayMs);
      }}
      onMouseLeave={() => {
        clearTimeout(timer.current);
        setOpen(false);
      }}
    >
      {children}
      {open && <PeekCard uriKey={uriKey} id={id} />}
    </span>
  );
}
