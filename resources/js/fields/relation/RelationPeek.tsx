import { request, useLathe, useQuery } from '@lathephp/core';
import { useRef, useState, type ReactNode } from 'react';
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

// Long enough that a pointer passing over a row opens nothing.
const hoverDelayMs = 300;

/** The card a peek request fills. Each record's payload stays in the query cache, so a second hover sends nothing. */
function PeekCard({ uriKey, id, top, left }: { uriKey: string; id: string | number; top: number; left: number }) {
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
    <div role="tooltip" style={{ position: 'fixed', top: `${String(top)}px`, left: `${String(left)}px` }} className="bg-popover text-popover-foreground z-50 w-72 rounded-md border p-3 shadow-md">
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

/** Wraps a related value so hovering it opens a card of the related record's peek fields. */
export function RelationPeek({ uriKey, id, children }: { uriKey: string; id: string | number; children: ReactNode }) {
  const [position, setPosition] = useState<{ top: number; left: number } | null>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  return (
    <span
      className="inline-block"
      onMouseEnter={(event) => {
        const trigger = event.currentTarget;
        timer.current = setTimeout(() => {
          const rect = trigger.getBoundingClientRect();
          setPosition({ top: rect.bottom + 4, left: rect.left });
        }, hoverDelayMs);
      }}
      onMouseLeave={() => {
        clearTimeout(timer.current);
        setPosition(null);
      }}
    >
      {children}
      {position !== null && <PeekCard uriKey={uriKey} id={id} top={position.top} left={position.left} />}
    </span>
  );
}
