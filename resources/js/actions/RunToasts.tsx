import { formatNumber, request, useLathe, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { runStore, type RunVerb, type StartedRun } from './runStore';

/** How often a running run asks the server for its progress, in milliseconds. */
export const RUN_POLL_MS = 2_000;


interface BulkStatus {
  status: 'waiting' | 'running' | 'finished' | 'failed' | 'cancelled';
  total: number;
  processed: number;
  failed: number;
  skipped: number;
}

export interface ExportStatus {
  status: 'waiting' | 'running' | 'finished' | 'failed';
  total_rows: number;
  processed_rows: number;
  download_url?: string | null;
}

export interface ImportStatus {
  status: 'uploaded' | 'queued' | 'processing' | 'completed' | 'failed';
  mode: 'dry_run' | 'import';
  total_rows: number;
  processed_rows: number;
  created_rows: number;
  updated_rows: number;
  skipped_rows: number;
  failed_rows: number;
  errors_url?: string | null;
}

type Status = BulkStatus | ExportStatus | ImportStatus;

type Run =
  | { kind: 'bulk'; id: string; verb: RunVerb; status: BulkStatus | null }
  | { kind: 'export'; id: string; status: ExportStatus | null }
  | { kind: 'import'; id: string; status: ImportStatus | null };

function done(run: Run): boolean {
  const status = run.status?.status;

  return status === 'finished' || status === 'failed' || status === 'cancelled' || status === 'completed';
}

/**
 * The progress toasts of queued runs on the page that started them: a bulk action or delete,
 * an export or an import. Each polls its run every 2 seconds until it ends, then shows the result.
 */
export function RunToasts() {
  const { __ } = useTranslation();
  const { apiPath, basePath } = useLathe();
  const started = useSyncExternalStore(runStore.subscribe, runStore.get);
  const [statuses, setStatuses] = useState<Record<string, Status | null>>({});
  const runs = started.map((run: StartedRun) => ({ ...run, status: statuses[`${run.kind}:${run.id}`] ?? null }) as Run);

  const active = runs.filter((run) => !done(run)).map((run) => `${run.kind}:${run.id}`).join(',');

  useEffect(() => {
    if (active === '') {
      return undefined;
    }

    const paths = { bulk: 'bulk-runs', export: 'exports', import: 'imports' };
    const timer = setInterval(() => {
      for (const key of active.split(',')) {
        const [kind, id] = key.split(':') as [Run['kind'], string];

        request<Status>(`${apiPath}/${paths[kind]}/${id}`)
          .then((status) => {
            setStatuses((current) => ({ ...current, [key]: status }));
          })
          .catch(() => undefined);
      }
    }, RUN_POLL_MS);

    return () => {
      clearInterval(timer);
    };
  }, [active, apiPath]);

  if (runs.length === 0) {
    return null;
  }

  function dismiss(run: Run) {
    runStore.dismiss(run.kind, run.id);
  }

  function cancel(run: Run) {
    void request(`${apiPath}/bulk-runs/${run.id}`, { method: 'DELETE' }).catch(() => undefined);
  }

  function text(run: Run): { message: string; body?: string } {
    if (run.kind === 'bulk') {
      const status = run.status;
      const total = formatNumber(status?.total ?? 0);
      const processed = formatNumber(status?.processed ?? 0);

      if (status === null || status.status === 'waiting' || status.status === 'running') {
        const progress: Record<RunVerb, string> = {
          action: __(':n of :total', { n: processed, total }),
          delete: __('Deleting :n of :total', { n: processed, total }),
          forceDelete: __('Deleting :n of :total', { n: processed, total }),
          restore: __('Restoring :n of :total', { n: processed, total }),
        };

        return { message: status === null ? __('This action is being processed...') : progress[run.verb] };
      }

      if (status.status === 'cancelled') {
        return { message: __('Cancelled.') };
      }

      const succeeded = status.processed - status.failed;

      if (status.failed > 0 && succeeded > 0) {
        return { message: __(':succeeded succeeded, :failed failed.', { succeeded: formatNumber(succeeded), failed: formatNumber(status.failed) }) };
      }

      if (status.status === 'failed' || status.failed > 0) {
        return { message: __('Action failed for all selected resources.') };
      }

      const finished: Record<RunVerb, string> = {
        action: __('Action was executed successfully.'),
        delete: __(':count resources deleted.', { count: processed }),
        forceDelete: __(':count resources permanently deleted.', { count: processed }),
        restore: __(':count resources restored.', { count: processed }),
      };

      return { message: finished[run.verb] };
    }

    if (run.kind === 'export') {
      const status = run.status;

      if (status?.status === 'finished') {
        return { message: __('Your export is ready for download.') };
      }

      if (status?.status === 'failed') {
        return { message: __('Your export failed.') };
      }

      return { message: status === null ? __('Export started.') : __('Exporting :n of :total', { n: formatNumber(status.processed_rows), total: formatNumber(status.total_rows) }) };
    }

    const status = run.status;

    if (status?.status === 'completed') {
      return {
        message: __('Your import has completed.'),
        body: __(':created created, :updated updated, :skipped skipped, :failed failed.', {
          created: status.created_rows,
          updated: status.updated_rows,
          skipped: status.skipped_rows,
          failed: status.failed_rows,
        }),
      };
    }

    if (status?.status === 'failed') {
      return { message: __('Your import failed.') };
    }

    return { message: status === null ? __('Import started.') : __('Importing :n of :total', { n: formatNumber(status.processed_rows), total: formatNumber(status.total_rows) }) };
  }

  const linkClass = 'rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring';

  return (
    <section aria-label={__('Runs')} className="fixed start-4 bottom-4 z-40 flex w-80 flex-col gap-2">
      {runs.map((run) => {
        const { message, body } = text(run);
        const ended = done(run);
        const exported = run.kind === 'export' ? run.status : null;
        const imported = run.kind === 'import' ? run.status : null;

        return (
          <div key={`${run.kind}:${run.id}`} role="status" className="flex items-start gap-3 rounded-lg border border-border bg-background p-3 text-sm shadow-lg">
            <div className="min-w-0 flex-1 space-y-1">
              <p>{message}</p>
              {body !== undefined && <p className="text-muted-foreground">{body}</p>}
              <div className="flex flex-wrap gap-3">
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
                {run.kind !== 'bulk' && (
                  <a href={`${basePath}/runs/${run.kind}/${run.id}`} className={linkClass}>
                    {run.kind === 'import' && run.status?.status === 'failed' ? __('Resume') : __('View run')}
                  </a>
                )}
                {run.kind === 'bulk' && !ended && (
                  <button
                    type="button"
                    className={linkClass}
                    onClick={() => {
                      cancel(run);
                    }}
                  >
                    {__('Cancel')}
                  </button>
                )}
              </div>
            </div>
            {ended && (
              <button
                type="button"
                aria-label={__('Dismiss')}
                className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onClick={() => {
                  dismiss(run);
                }}
              >
                <X aria-hidden="true" className="size-4" />
              </button>
            )}
          </div>
        );
      })}
    </section>
  );
}
