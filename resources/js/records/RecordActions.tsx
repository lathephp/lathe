import { router } from '@inertiajs/react';
import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useId, useState } from 'react';
import { Modal, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import type { DetailRecord } from '../fields/types';
import { Button } from '../components/ui/Button';

type Action = 'delete' | 'restore' | 'forceDelete';

// The helper loads on the click, so the detail page's first load leaves it out.
const startImpersonating = (basePath: string, resource: string, resourceId: string | number) =>
  import('../layout/impersonate').then((module) => module.impersonate(basePath, { resource, resourceId }));

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
        <Button
          disabled={busy}
          variant="outline"
          onClick={() => {
            setBusy(true);
            startImpersonating(basePath, uriKey, record.id).catch(() => {
              setBusy(false);
            });
          }}
        >
          {__('Impersonate')}
        </Button>
      ) : null}
      {!trashed && abilities.delete ? (
        <Button
          disabled={busy}
          variant="destructive"
          onClick={() => {
            setConfirming('delete');
          }}
        >
          {__('Delete')}
        </Button>
      ) : null}
      {trashed && abilities.restore ? (
        <Button
          disabled={busy}
          variant="outline"
          onClick={() => {
            void run('restore');
          }}
        >
          {__('Restore')}
        </Button>
      ) : null}
      {trashed && abilities.forceDelete ? (
        <Button
          disabled={busy}
          variant="destructive"
          onClick={() => {
            setConfirming('forceDelete');
          }}
        >
          {__('Force Delete')}
        </Button>
      ) : null}
      {confirming === null ? null : (
        <Modal
          labelledBy={titleId}
          onClose={() => {
            setConfirming(null);
          }}
        >
          <ModalHeader>
            <ModalTitle id={titleId}>
              {confirming === 'delete' ? __('Delete :title?', { title: record.title }) : __('Delete :title for good?', { title: record.title })}
            </ModalTitle>
            <ModalDescription>{confirming === 'delete' ? __('This cannot be undone from here.') : __('This cannot be undone.')}</ModalDescription>
          </ModalHeader>
          <ModalFooter>
            <Button
              variant="outline"
              onClick={() => {
                setConfirming(null);
              }}
            >
              {__('Cancel')}
            </Button>
            <Button
              variant="destructive-solid"
              onClick={() => {
                void run(confirming);
              }}
            >
              {confirming === 'delete' ? __('Delete') : __('Force Delete')}
            </Button>
          </ModalFooter>
        </Modal>
      )}
    </>
  );
}
