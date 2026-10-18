import { router, usePage } from '@inertiajs/react';
import { ApiError, emit, toast, useTranslation, visit } from '@lathephp/core';
import { lazy, Suspense, useCallback, useState, type ReactNode } from 'react';
import type { SelectionDescriptor } from '../index/useRowSelection';
import { ReauthModal } from '../records/ReauthModal';
import type { SharedProps } from '../types';
import { applyActionResult } from './result';
import { runAction } from './runAction';
import type { ActionMeta } from './types';

// The modals load on first use, so the field components stay out of the index's first load.
const ActionModal = lazy(() => import('./ActionModal').then((module) => ({ default: module.ActionModal })));
const ResponseModal = lazy(() => import('./ResponseModal').then((module) => ({ default: module.ResponseModal })));
const ImportModal = lazy(() => import('./ImportModal').then((module) => ({ default: module.ImportModal })));

interface Running {
  action: ActionMeta;
  selection: SelectionDescriptor;
  count: number;
}

interface ActionRunnerOptions {
  /** The API base of the Resource or lens: .../resources/orders or .../resources/orders/lenses/{lens}. */
  base: string;
  /** After a run that did not navigate away, such as reloading the rows. */
  onDone: (action: ActionMeta) => void;
}

/**
 * Runs actions: the modal when one confirms, the run itself, its result, a queued run's
 * progress toast, the re-auth modal on 401 and 419, and a Retry toast on any other failure.
 */
export function useActionRunner({ base, onDone }: ActionRunnerOptions): { start: (action: ActionMeta, selection: SelectionDescriptor, count: number) => void; element: ReactNode } {
  const { __ } = useTranslation();
  const { auth } = usePage<SharedProps>().props;
  const [running, setRunning] = useState<Running | null>(null);
  const [response, setResponse] = useState<{ component: string; data: Record<string, unknown> } | null>(null);
  const [reauth, setReauth] = useState<(() => void) | null>(null);
  const closeRunning = useCallback(() => {
    setRunning(null);
  }, []);
  const closeResponse = useCallback(() => {
    setResponse(null);
  }, []);

  async function execute(action: ActionMeta, selection: SelectionDescriptor, fields: Record<string, unknown>, inModal: boolean): Promise<void> {
    try {
      const run = await runAction(`${base}/action`, action.uriKey, selection, fields);
      setRunning(null);
      emit('actions:ran', { action: action.uriKey });
      // A queued run's own toast says it started, so its message would say it twice.
      const queued = run.bulk_run !== undefined || 'export_run' in run;

      applyActionResult(queued ? { ...run.result, message: null } : run.result, {
        openModal: (component, data) => {
          setResponse({ component, data });
        },
        refresh: () => undefined,
        visit: (path, options) => {
          visit(path, options);
        },
      });

      if (run.bulk_run !== undefined) {
        window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { ...run.bulk_run, resource: /\/resources\/([^/]+)/.exec(base)?.[1] } }));
      }

      if ('export_run' in run) {
        window.dispatchEvent(new CustomEvent('lathe:export-started', { detail: run.export_run }));
      }

      if (run.result.effect?.type !== 'redirect' && run.result.effect?.type !== 'visit') {
        onDone(action);
      }
    } catch (error) {
      if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
        setReauth(() => () => {
          void execute(action, selection, fields, inModal).catch(() => undefined);
        });
      } else if (error instanceof ApiError && error.status === 423) {
        // The password check was cancelled; the dialog is still open to try again.
      } else if (error instanceof ApiError && error.status === 422) {
        const message = firstMessage(error.data);

        // A modal shows the messages under its fields; with none open, the first one toasts.
        if (!inModal && message !== null) {
          toast.error(message);
        }

        throw error;
      } else {
        toast.error(__('Something went wrong. Please try again.'), {
          action: {
            label: __('Retry'),
            onClick: () => {
              void execute(action, selection, fields, inModal).catch(() => undefined);
            },
          },
        });
      }
    }
  }

  function start(action: ActionMeta, selection: SelectionDescriptor, count: number) {
    if (action.withoutConfirmation) {
      // A file action takes a moment before the download starts.
      if (action.asBlob) {
        toast.info(__('Preparing file'));
      }

      void execute(action, selection, {}, false).catch(() => undefined);
    } else {
      setRunning({ action, selection, count });
    }
  }

  // An import action's metadata carries import: true; it opens the import's own steps.
  const importing = running !== null && 'import' in running.action && running.action.import === true;

  const element = (
    <Suspense fallback={null}>
      {running !== null && importing && <ImportModal base={base} action={running.action} selection={running.selection} onClose={closeRunning} />}
      {running !== null && !importing && (
        <ActionModal
          base={base}
          action={running.action}
          count={running.count}
          resourceId={running.selection.mode === 'ids' && running.selection.ids.length === 1 ? (running.selection.ids[0] ?? null) : null}
          onClose={closeRunning}
          onRun={(fields) => execute(running.action, running.selection, fields, true)}
        />
      )}
      {response !== null && <ResponseModal component={response.component} data={response.data} onClose={closeResponse} />}
      {reauth !== null && (
        <ReauthModal
          email={auth.user?.email ?? ''}
          onSignedIn={() => {
            const retry = reauth;
            setReauth(null);
            retry();
          }}
          onOtherUser={() => {
            setReauth(null);
            router.reload();
          }}
          onClose={() => {
            setReauth(null);
          }}
        />
      )}
    </Suspense>
  );

  return { start, element };
}

function firstMessage(data: unknown): string | null {
  const errors = (data as { errors?: Record<string, string[] | undefined> } | null)?.errors;
  const first = errors === undefined ? undefined : Object.values(errors)[0]?.[0];

  return first ?? null;
}
