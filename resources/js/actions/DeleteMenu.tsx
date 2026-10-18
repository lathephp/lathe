import { router } from '@inertiajs/react';
import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { Row } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
import type { RowSelection } from '../index/useRowSelection';
import { batchOperations, type BatchOperation } from './batchOperations';


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

const itemClass =
  'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

/** Delete, force delete and restore over the selection, each after a confirm naming the count. */
export function DeleteMenu({ uriKey, lens, softDeletes, selection, rows, total, state }: DeleteMenuProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const menuId = useId();
  const titleId = useId();
  const [open, setOpen] = useState(false);
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
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { ...result.bulk_run, verb: operation } }));

      return;
    }

    const done = operation === 'restore' ? (result?.restored ?? 0) : (result?.deleted ?? 0);
    const messages: Record<BatchOperation, string> = {
      delete: __(':count resource(s) deleted.', { count: done }),
      forceDelete: __(':count resource(s) permanently deleted.', { count: done }),
      restore: __(':count resource(s) restored.', { count: done }),
    };
    toast.success(messages[operation]);
    router.reload({ only: ['table', 'count'] });
  }

  return (
    <div className="relative">
      <Button
        variant="outline"
        size="sm"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => {
          setOpen(!open);
        }}
      >
        {__('Delete')}
        <ChevronDown aria-hidden="true" className="size-4" />
      </Button>
      {open && (
        <div
          id={menuId}
          role="menu"
          aria-label={__('Delete')}
          tabIndex={-1}
          className="absolute end-0 top-full z-20 mt-1 w-56 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
        >
          {operations.map((operation) => (
            <button
              key={operation}
              type="button"
              role="menuitem"
              className={operation === 'restore' ? itemClass : `${itemClass} text-destructive`}
              onClick={() => {
                setOpen(false);
                setConfirming(operation);
              }}
            >
              {labels[operation]}
            </button>
          ))}
        </div>
      )}
      {confirming !== null && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-scrim p-4">
          <div role="alertdialog" aria-modal="true" aria-labelledby={titleId} className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-background p-4 shadow-lg">
            <h2 id={titleId} className="text-base font-semibold">
              {questions[confirming]}
            </h2>
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setConfirming(null);
                }}
              >
                {__('Cancel')}
              </Button>
              {/* The ui Button has no destructive variant, and its classes do not merge with an override. */}
              <button
                type="button"
                className="inline-flex h-8 items-center rounded-md bg-destructive px-3 text-sm font-medium text-destructive-foreground outline-none hover:bg-destructive/90 focus-visible:ring-2 focus-visible:ring-ring"
                onClick={() => {
                  void run(confirming);
                }}
              >
                {labels[confirming]}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
