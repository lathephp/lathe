import { formatDateTime, request, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useState } from 'react';
import { Card } from '../components/ui/Card';
import { skeletonBar } from '../lib/instant';

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

    request<{ events: HistoryEvent[] }>(`${apiPath}/resources/${uriKey}/${String(id)}/history`, { signal: abort.signal, toastOnError: false }).then(
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
    <Card>
      <header className="flex items-center justify-between border-b border-border px-4 py-3">
        <h2 className="text-base font-semibold">{__('History')}</h2>
        {events !== null && events.length > 0 ? (
          <button type="button" className="text-sm font-medium text-primary hover:underline" onClick={onShowAll}>
            {__('Show all')}
          </button>
        ) : null}
      </header>
      {events === null ? (
        <div aria-busy="true" className="space-y-3 px-4 py-4">
          <div className={`h-3 w-2/3 ${skeletonBar}`} />
          <div className={`h-3 w-1/2 ${skeletonBar}`} />
        </div>
      ) : events.length === 0 ? (
        <p className="px-4 py-4 text-sm text-muted-foreground">{__('No changes yet')}</p>
      ) : (
        <ol className="space-y-3 px-4 py-4">
          {events.map((event) => (
            <li key={event.id} className="flex flex-col gap-0.5 text-sm">
              <span>
                {event.user === null ? null : <strong className="font-semibold">{event.user} </strong>}
                {event.title}
              </span>
              {event.at === null ? null : <span className="text-xs text-muted-foreground">{formatDateTime(event.at)}</span>}
            </li>
          ))}
        </ol>
      )}
    </Card>
  );
}
