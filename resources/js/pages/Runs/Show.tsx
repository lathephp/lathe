import { Head } from '@inertiajs/react';
import { formatNumber, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Button, Card } from '@lathephp/ui';
import { useEffect, useState } from 'react';
import { RUN_POLL_MS, type ExportStatus, type ImportStatus } from '../../actions/RunToasts';

interface RunPageProps {
  type: 'export' | 'import';
  id: number;
}

type Status = (ExportStatus | ImportStatus) & { exception?: string | null };

function ended(status: Status | null): boolean {
  return status !== null && ['finished', 'completed', 'failed'].includes(status.status);
}

/**
 * The page of a queued export or import: its status and counters, then its result. It works
 * whether or not notifications are on.
 */
export default function Show({ type, id }: RunPageProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [status, setStatus] = useState<Status | null>(null);
  const url = `${apiPath}/${type === 'export' ? 'exports' : 'imports'}/${String(id)}`;
  const done = ended(status);

  useEffect(() => {
    let live = true;

    function load() {
      request<Status>(url, { toastOnError: false })
        .then((next) => {
          if (live) {
            setStatus(next);
          }
        })
        .catch(() => undefined);
    }

    const first = setTimeout(load, 0);
    const timer = done ? null : setInterval(load, RUN_POLL_MS);

    return () => {
      live = false;
      clearTimeout(first);

      if (timer !== null) {
        clearInterval(timer);
      }
    };
  }, [url, done]);

  async function resume() {
    try {
      await request(`${url}/resume`, { method: 'POST', toastOnError: false });
      setStatus(null);
    } catch {
      toast.error(__('Something went wrong. Please try again.'));
    }
  }

  const title = type === 'export' ? __('Export #:id', { id }) : __('Import #:id', { id });
  const imported = type === 'import' && status !== null ? (status as ImportStatus) : null;
  const exported = type === 'export' && status !== null ? (status as ExportStatus) : null;
  const linkClass = 'text-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring';

  return (
    <div className="space-y-6">
      <Head title={title} />
      <h1 className="text-2xl font-semibold">{title}</h1>
      <Card className="space-y-3 p-4 text-sm">
        {status === null ? (
          <p className="text-muted-foreground">{__('Loading')}</p>
        ) : (
          <>
            <dl className="grid grid-cols-[minmax(0,10rem)_minmax(0,1fr)] gap-2">
              <dt className="text-muted-foreground">{__('Status')}</dt>
              <dd>{status.status}</dd>
              <dt className="text-muted-foreground">{__('Processed')}</dt>
              <dd>
                {formatNumber(status.processed_rows)} / {formatNumber(status.total_rows)}
              </dd>
            </dl>
            {imported !== null && imported.status === 'completed' && (
              <p>
                {__(':created created, :updated updated, :skipped skipped, :failed failed.', {
                  created: imported.created_rows,
                  updated: imported.updated_rows,
                  skipped: imported.skipped_rows,
                  failed: imported.failed_rows,
                })}
              </p>
            )}
            {status.status === 'failed' && (
              <div role="alert" className="space-y-1">
                <p className="font-medium text-destructive">{type === 'export' ? __('Your export failed.') : __('Your import failed.')}</p>
                {status.exception != null && <p className="text-muted-foreground">{status.exception}</p>}
              </div>
            )}
            <div className="flex flex-wrap items-center gap-3">
              {exported?.download_url != null && (
                <a href={exported.download_url} target="_blank" rel="noopener noreferrer" className={linkClass}>
                  {__('Download')}
                </a>
              )}
              {imported?.errors_url != null && imported.failed_rows > 0 && (
                <a href={imported.errors_url} target="_blank" rel="noopener noreferrer" className={linkClass}>
                  {__('Download Failed Rows')}
                </a>
              )}
              {imported !== null && imported.status === 'failed' && (
                <Button
                  size="sm"
                  onClick={() => {
                    void resume();
                  }}
                >
                  {__('Resume')}
                </Button>
              )}
            </div>
          </>
        )}
      </Card>
    </div>
  );
}
