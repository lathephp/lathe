import { formatDateTime, request, useLathe, useTranslation } from '@lathephp/core';
import { Link2, Pencil, Play, Plus, RotateCcw, Trash2, Unlink, UserRound, type LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Card } from '../components/ui/Card';
import { cn } from '../lib/cn';
import { skeletonBar } from '../lib/instant';

// The mark beside each event, by its kind; a kind not named here shows the pencil.
const icons: Partial<Record<string, LucideIcon>> = {
  create: Plus,
  delete: Trash2,
  'force-delete': Trash2,
  restore: RotateCcw,
  attach: Link2,
  detach: Unlink,
  'action-ran': Play,
  'impersonation-started': UserRound,
  'impersonation-stopped': UserRound,
};

interface HistoryEvent {
  id: string | number;
  type: string;
  title: string;
  user: string | null;
  at: string | null;
}

/** A record's newest action events, read when the card mounts; Show all opens the full log. */
export function HistoryCard({ uriKey, id, onShowAll }: { uriKey: string; id: string | number; onShowAll: () => void }) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [events, setEvents] = useState<HistoryEvent[] | null>(null);

  useEffect(() => {
    const abort = new AbortController();

    request<{ events: HistoryEvent[] }>(`${apiPath}/resources/${uriKey}/${String(id)}/history/latest`, { signal: abort.signal, toastOnError: false }).then(
      (reply) => {
        setEvents(reply?.events ?? []);
      },
      () => {
        setEvents([]);
      },
    );

    return () => {
      abort.abort();
    };
  }, [apiPath, uriKey, id]);

  return (
    <Card role="region" aria-label={__('History')}>
      <header className="flex items-center justify-between border-b border-border px-5 py-3.5">
        <h2 className="text-base font-semibold">{__('History')}</h2>
        {events !== null && events.length > 0 ? (
          <button type="button" className="rounded-sm text-[13px] font-medium text-link outline-none hover:text-link-hover focus-visible:ring-2 focus-visible:ring-focus" onClick={onShowAll}>
            {__('Show all')}
          </button>
        ) : null}
      </header>
      {events === null ? (
        <div aria-busy="true" className="space-y-3 px-5 py-4">
          <div className={`h-3 w-2/3 ${skeletonBar}`} />
          <div className={`h-3 w-1/2 ${skeletonBar}`} />
        </div>
      ) : events.length === 0 ? (
        <p className="px-5 py-4 text-[13px] text-muted-foreground">{__('No changes yet')}</p>
      ) : (
        <ol className="flex flex-col gap-4 px-5 py-4">
          {events.map((event) => {
            const Icon = icons[event.type] ?? Pencil;

            return (
              <li key={event.id} className="grid grid-cols-[24px_minmax(0,1fr)] gap-2.5">
                <span className={cn('inline-flex size-6 items-center justify-center rounded-full', event.type === 'create' ? 'bg-primary-subtle text-primary-subtle-foreground' : 'bg-muted text-muted-foreground')}>
                  <Icon aria-hidden="true" strokeWidth={2.5} className="size-3" />
                </span>
                <span className="flex flex-col gap-0.5">
                  <span className="text-[13px]">
                    {event.user === null ? null : <strong className="font-semibold">{event.user} </strong>}
                    {event.title}
                  </span>
                  {event.at === null ? null : <span className="text-xs text-muted-foreground tabular-nums">{formatDateTime(event.at)}</span>}
                </span>
              </li>
            );
          })}
        </ol>
      )}
    </Card>
  );
}
