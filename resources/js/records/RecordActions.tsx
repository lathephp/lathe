import { router } from '@inertiajs/react';
import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useId, useState } from 'react';
import { Modal } from '../components/Modal';
import type { DetailRecord } from '../fields/types';

type Action = 'delete' | 'restore' | 'forceDelete';

interface RecordActionsProps {
  uriKey: string;
  record: DetailRecord;
}

/**
 * Delete on a live record; Restore and Force Delete on a trashed one. Each shows only when the
 * user holds its ability; the destructive two ask first.
 */
export function RecordActions({ uriKey, record }: RecordActionsProps) {
  const { __ } = useTranslation();
  const { apiPath, basePath } = useLathe();
  const titleId = useId();
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  const [busy, setBusy] = useState(false);
  const url = `${apiPath}/resources/${uriKey}/${String(record.id)}`;
  const { abilities, trashed } = record;

  async function run(action: Action) {
    setConfirming(null);
    setBusy(true);
    const requests: Record<Action, { path: string; method: string; done: string; failed: string }> = {
      delete: { path: url, method: 'DELETE', done: __(':title was deleted.', { title: record.title }), failed: __(':title could not be deleted.', { title: record.title }) },
      restore: { path: `${url}/restore`, method: 'PUT', done: __(':title was restored.', { title: record.title }), failed: __(':title could not be restored.', { title: record.title }) },
      forceDelete: { path: `${url}/force`, method: 'DELETE', done: __(':title was deleted for good.', { title: record.title }), failed: __(':title could not be deleted.', { title: record.title }) },
    };
    const call = requests[action];

    try {
      const reply = await request<{ redirect?: string }>(call.path, { method: call.method, toastOnError: false });
      toast.success(call.done);

      if (action === 'restore') {
        router.reload();
      } else {
        router.visit(`${basePath}/resources/${reply?.redirect ?? uriKey}`);
      }
    } catch (failure) {
      const message = failure instanceof ApiError ? (failure.data as { message?: string } | null)?.message : undefined;
      toast.error(failure instanceof ApiError && failure.status === 403 && message !== undefined ? message : call.failed);
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      {!trashed && abilities.impersonate === true ? (
        <button
          type="button"
          disabled={busy}
          className="inline-flex h-9 items-center rounded-md border border-input px-4 text-sm font-medium"
          onClick={() => {
            setBusy(true);
            // The helper loads on the click, so the detail page's first load leaves it out.
            import('../layout/impersonate')
              .then((module) => module.impersonate(basePath, { resource: uriKey, resourceId: record.id }))
              .catch(() => {
                setBusy(false);
              });
          }}
        >
          {__('Impersonate')}
        </button>
      ) : null}
      {!trashed && abilities.delete ? (
        <button
          type="button"
          disabled={busy}
          className="inline-flex h-9 items-center rounded-md border border-input px-4 text-sm font-medium text-destructive"
          onClick={() => {
            setConfirming('delete');
          }}
        >
          {__('Delete')}
        </button>
      ) : null}
      {trashed && abilities.restore ? (
        <button
          type="button"
          disabled={busy}
          className="inline-flex h-9 items-center rounded-md border border-input px-4 text-sm font-medium"
          onClick={() => {
            void run('restore');
          }}
        >
          {__('Restore')}
        </button>
      ) : null}
      {trashed && abilities.forceDelete ? (
        <button
          type="button"
          disabled={busy}
          className="inline-flex h-9 items-center rounded-md border border-input px-4 text-sm font-medium text-destructive"
          onClick={() => {
            setConfirming('forceDelete');
          }}
        >
          {__('Force Delete')}
        </button>
      ) : null}
      {confirming === null ? null : (
        <Modal
          labelledBy={titleId}
          onClose={() => {
            setConfirming(null);
          }}
        >
          <h2 id={titleId} className="text-base font-semibold">
            {confirming === 'delete' ? __('Delete :title?', { title: record.title }) : __('Delete :title for good?', { title: record.title })}
          </h2>
          <p className="text-sm text-muted-foreground">
            {confirming === 'delete' ? __('This cannot be undone from here.') : __('This cannot be undone.')}
          </p>
          <div className="flex justify-end gap-2">
            <button
              type="button"
              className="h-9 rounded-md px-4 text-sm"
              onClick={() => {
                setConfirming(null);
              }}
            >
              {__('Cancel')}
            </button>
            <button
              type="button"
              className="h-9 rounded-md bg-destructive px-4 text-sm font-medium text-white"
              onClick={() => {
                void run(confirming);
              }}
            >
              {confirming === 'delete' ? __('Delete') : __('Force Delete')}
            </button>
          </div>
        </Modal>
      )}
    </>
  );
}
