import { __panel, formatNumber, request, useLathe, useTranslation, type ToastLink, type ToastMessage, type ToastType } from '@lathephp/core';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, useSyncExternalStore } from 'react';
import { runStore, type RunVerb, type StartedRun } from './runStore';
import { reloadIndexRows } from '../lib/instant';

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
  resource_label?: string | null;
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
 * Once a bulk run ends, drops the prefetched pages of its resource and, when the person is still on that
 * index or one of its lenses, reloads its rows in place. The toasts sit beside Inertia's page, so the
 * address bar says where the person is.
 */
function refreshIndex(resource: string, basePath: string): void {
  router.flushByCacheTags(`resource:${resource}`);

  if (new RegExp(`^${basePath}/resources/${resource}(/lens/[^/]+)?/?$`).test(window.location.pathname)) {
    reloadIndexRows();
  }
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
  const reloaded = useRef(new Set<string>());
  const shown = useRef(new Map<string, { id: number; toast: Omit<ToastMessage, 'id'> }>());
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

        request<Status>(`${apiPath}/${paths[kind]}/${id}`, { toastOnError: false })
          .then((status) => {
            setStatuses((current) => ({ ...current, [key]: status }));

            const started = runStore.get().find((run) => run.kind === 'bulk' && run.id === id);

            if (started?.kind === 'bulk' && started.resource !== undefined && status !== null && status.status !== 'waiting' && status.status !== 'running' && !reloaded.current.has(id)) {
              reloaded.current.add(id);
              refreshIndex(started.resource, basePath);
            }
          })
          .catch(() => undefined);
      }
    }, RUN_POLL_MS);

    return () => {
      clearInterval(timer);
    };
  }, [active, apiPath, basePath]);

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

      // A row the policy refused never ran, so the run is not read as a plain success.
      if (run.verb === 'action' && status.skipped > 0) {
        return { message: __(':succeeded succeeded, :skipped skipped.', { succeeded: formatNumber(succeeded), skipped: formatNumber(status.skipped) }) };
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

    if (status?.status === 'completed' && status.failed_rows > 0) {
      return {
        message: __(':imported of :total :resource imported', {
          imported: formatNumber(status.total_rows - status.failed_rows),
          total: formatNumber(status.total_rows),
          resource: (status.resource_label ?? __('Rows')).toLocaleLowerCase(),
        }),
      };
    }

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

  // One toast per run, keyed by run and updated in place; it stays while the run runs and when it needs a look.
  useEffect(() => {
    for (const run of runs) {
      const key = `${run.kind}:${run.id}`;
      const { message, body } = text(run);
      const status = run.status;
      const ended = done(run);
      const links: ToastLink[] = [];
      let type: ToastType = ended ? 'success' : 'info';

      if (run.kind === 'export' && run.status?.download_url != null) {
        links.push({ label: __('Download'), href: run.status.download_url, newTab: true });
      }

      if (run.kind === 'import' && run.status?.errors_url != null && run.status.failed_rows > 0) {
        links.push({ label: __('Download failed rows'), href: run.status.errors_url, newTab: true });
      }

      if (run.kind !== 'bulk') {
        links.push({ label: run.kind === 'import' && status?.status === 'failed' ? __('Resume') : __('View run'), href: `${basePath}/runs/${run.kind}/${run.id}`, newTab: false });
      }

      if (status?.status === 'failed' || (run.kind === 'bulk' && run.status !== null && run.status.failed > 0 && run.status.failed === run.status.processed)) {
        type = 'error';
      } else if ((run.kind === 'bulk' && run.status !== null && (run.status.failed > 0 || run.status.skipped > 0)) || (run.kind === 'import' && (run.status?.failed_rows ?? 0) > 0)) {
        type = 'warning';
      }

      const next: Omit<ToastMessage, 'id'> = {
        type,
        message,
        ...(body === undefined ? {} : { body }),
        links,
        ...(run.kind === 'bulk' && !ended
          ? {
              action: {
                label: __('Cancel'),
                onClick: () => {
                  cancel(run);
                },
              },
            }
          : {}),
        timeout: ended && type === 'success' ? 5_000 : 0,
        dismissible: ended,
      };
      const previous = shown.current.get(key);

      if (previous === undefined || JSON.stringify(previous.toast) !== JSON.stringify(next)) {
        shown.current.set(key, { id: __panel.putToast(next, previous?.id), toast: next });
      }
    }
  });

  // A toast the person dismissed, or one whose timer ran out, ends its run here too.
  useEffect(
    () =>
      __panel.toasts.subscribe(() => {
        const ids = new Set(__panel.toasts.get().map((toast) => toast.id));

        shown.current.forEach(({ id }, key) => {
          if (!ids.has(id)) {
            shown.current.delete(key);
            const [kind, runId] = key.split(':') as [Run['kind'], string];
            runStore.dismiss(kind, runId);
          }
        });
      }),
    [],
  );

  return null;
}
