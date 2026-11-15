import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { Modal, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import type { Row } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
import { clearUndo } from '../index/undo';
import type { RowSelection } from '../index/useRowSelection';
import { batchOperations, type BatchOperation } from './batchOperations';
import { reloadIndexRows } from '../lib/instant';


interface DeleteMenuProps {
  uriKey: string;
  lens: string | null;
  softDeletes: boolean;
  selection: RowSelection;
  rows: Row[];
  total: number | null;
  state: IndexUrlState;
}

interface BatchResult {
  deleted?: number;
  restored?: number;
  skipped: number;
  bulk_run?: { id: string; total: number };
}

/** Delete, force delete and restore over the selection, each after a confirm naming the count. */
export function DeleteMenu({ uriKey, lens, softDeletes, selection, rows, total, state }: DeleteMenuProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const titleId = useId();
  const [confirming, setConfirming] = useState<BatchOperation | null>(null);
  const operations = batchOperations(softDeletes, selection, rows);
  const count = selection.all ? (total ?? 0) : selection.selected.size;
  const base = lens === null ? `${apiPath}/resources/${uriKey}` : `${apiPath}/resources/${uriKey}/lenses/${lens}`;

  if (operations.length === 0) {
    return null;
  }

  const labels: Record<BatchOperation, string> = {
    delete: __('Delete Selected'),
    forceDelete: __('Force Delete Selected'),
    restore: __('Restore Selected'),
  };
  const questions: Record<BatchOperation, string> = {
    delete: __('Delete :count resource(s)?', { count }),
    forceDelete: __('Permanently delete :count resource(s)?', { count }),
    restore: __('Restore :count resource(s)?', { count }),
  };

  async function run(operation: BatchOperation) {
    setConfirming(null);
    const descriptor = selection.descriptor(state, lens);
    const routes: Record<BatchOperation, { path: string; method: string }> = {
      delete: { path: 'batch', method: 'DELETE' },
      forceDelete: { path: 'batch/force', method: 'DELETE' },
      restore: { path: 'batch/restore', method: 'PUT' },
    };
    const { path, method } = routes[operation];

    let result: BatchResult | null;

    try {
      clearUndo();
      result = await request<BatchResult>(`${base}/${path}`, { method, body: { selection: descriptor }, toastOnError: false });
    } catch {
      toast.error(__('Operation failed. Please try again.'), {
        action: {
          label: __('Retry'),
          onClick: () => {
            void run(operation);
          },
        },
      });

      return;
    }

    selection.clear();

    if (result?.bulk_run !== undefined) {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { ...result.bulk_run, verb: operation, resource: uriKey } }));

      return;
    }

    const done = operation === 'restore' ? (result?.restored ?? 0) : (result?.deleted ?? 0);
    const messages: Record<BatchOperation, string> = {
      delete: __(':count resource(s) deleted.', { count: done }),
      forceDelete: __(':count resource(s) permanently deleted.', { count: done }),
      restore: __(':count resource(s) restored.', { count: done }),
    };
    toast.success(messages[operation]);
    reloadIndexRows();
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
          {__('Delete')}
          <ChevronDown aria-hidden="true" className="size-4" />
        </DropdownMenuTrigger>
        {/* On the body, above the sidebar; it ends at the button's end edge and moves in to stay 8 px inside the window. */}
        <DropdownMenuContent align="end" collisionPadding={8} aria-label={__('Delete')} className="w-56">
          {operations.map((operation) => (
            <DropdownMenuItem
              key={operation}
              variant={operation === 'restore' ? 'default' : 'destructive'}
              onClick={() => {
                setConfirming(operation);
              }}
            >
              {labels[operation]}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
      {confirming !== null && (
        <Modal
          role="alertdialog"
          labelledBy={titleId}
          onClose={() => {
            setConfirming(null);
          }}
        >
          <ModalHeader>
            <ModalTitle id={titleId}>{questions[confirming]}</ModalTitle>
          </ModalHeader>
          <ModalFooter>
            {/* Before a delete the safe button says what it keeps; it comes first, so it holds the focus. */}
            <Button
              variant="outline"
              onClick={() => {
                setConfirming(null);
              }}
            >
              {confirming === 'restore' ? __('Cancel') : __('Keep :count resource(s)', { count })}
            </Button>
            <Button
              variant={confirming === 'restore' ? 'default' : 'destructive-solid'}
              onClick={() => {
                void run(confirming);
              }}
            >
              {labels[confirming]}
            </Button>
          </ModalFooter>
        </Modal>
      )}
    </>
  );
}
