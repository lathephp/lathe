import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useId, useRef, useState } from 'react';
import type { Cell, FieldSchema } from '../fields/types';
import { stringValue } from '../fields/registry';
import { lazyNamed } from '../lib/lazy';
import { TableCell } from '../table/cells';
import { ConflictDialog } from './lazy';

// The controls load on the first edit, so a list's first paint carries only the values.
const InlineEditor = lazyNamed(() => import('./InlineEditor').then((module) => module.InlineEditor));

interface InlineReply {
  fields: Record<string, Cell>;
  updatedAt?: string | null;
}

interface InlineCellProps {
  uriKey: string;
  rowId: string | number;
  column: FieldSchema;
  cell: Cell;
  token: string | null | undefined;
  /** The fresh cells of the whole row and its new token, after a save or a conflict. */
  onRow: (fields: Record<string, Cell>, updatedAt: string | null | undefined) => void;
}

interface Option {
  value: string;
  label: string;
}

/**
 * An index cell that saves in place. It shows its value like any cell until the grid asks for an
 * edit (Enter, F2 or a double-click): a boolean flips at once, any other opens its control.
 * One request at a time; a refused value shows its message on the cell and the old value comes back.
 */
export function InlineCell({ uriKey, rowId, column, cell, token, onRow }: InlineCellProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const errorId = useId();
  const root = useRef<HTMLDivElement>(null);
  const [pending, setPending] = useState<Cell | null>(null);
  const [editing, setEditing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // The undo outlives this render: the toast calls it later, so it reads the latest token and row callback from refs.
  const latest = useRef({ token, onRow });
  useEffect(() => {
    latest.current = { token, onRow };
  });
  const [conflict, setConflict] = useState<unknown>(undefined);
  const shown = pending ?? cell;
  const busy = pending !== null;
  // What the grid's edit request reads when it comes, after this render.
  const current = useRef({ busy, shown, commit });
  useEffect(() => {
    current.current = { busy, shown, commit };
  });
  const options = (column.options as Option[] | undefined) ?? [];

  function send(value: unknown, extra: Record<string, unknown>, updatedAt: string | undefined) {
    return request<InlineReply>(`${apiPath}/resources/${uriKey}/${String(rowId)}/field/${column.key}`, {
      method: 'PATCH',
      toastOnError: false,
      body: { value, ...extra, _updated_at: updatedAt },
    });
  }

  /**
   * Saves the value the cell had before as a new change, against the token its own save returned:
   * a record changed since, even one a poll already showed, opens the conflict dialog.
   */
  async function undo(before: unknown, updatedAt: string | null | undefined) {
    setPending({ value: before, display: displayOf(before) });

    try {
      const reply = await send(before, {}, updatedAt ?? undefined);

      if (reply !== null) {
        latest.current.onRow(reply.fields, reply.updatedAt);
        latest.current.token = reply.updatedAt;
      }

      setConflict(undefined);
    } catch (failure) {
      const reply = failure instanceof ApiError && failure.status === 409 ? (failure.data as InlineReply | null) : null;

      if (reply?.fields === undefined) {
        setConflict(undefined);
        toast.error(__('Something went wrong. Please try again.'));
      } else {
        latest.current.onRow(reply.fields, reply.updatedAt);
        latest.current.token = reply.updatedAt;
        setConflict({ before });
      }
    } finally {
      setPending(null);
    }
  }

  async function commit(value: unknown, shownWhilePending?: Cell, extra: Record<string, unknown> = {}) {
    if (busy) {
      return;
    }

    setError(null);
    setPending(shownWhilePending ?? { value, display: displayOf(value) });

    try {
      const reply = await send(value, extra, token ?? undefined);

      if (reply !== null) {
        onRow(reply.fields, reply.updatedAt);
        latest.current.token = reply.updatedAt;

        if (column.component !== 'belongs-to-field' && column.component !== 'morph-to-field') {
          const before = cell.value;
          const display = reply.fields[column.key]?.display ?? displayOf(value);

          toast.success(__(':field changed to :value.', { field: column.label, value: display }), {
            action: { label: __('Undo'), onClick: () => void undo(before, reply.updatedAt) },
          });
        }
      }
    } catch (failure) {
      if (failure instanceof ApiError && failure.status === 422) {
        const errors = (failure.data as { errors?: Record<string, string[]> } | null)?.errors ?? {};
        setError(Object.values(errors)[0]?.[0] ?? __('Please fix the highlighted fields.'));
      } else if (failure instanceof ApiError && failure.status === 409) {
        const reply = failure.data as InlineReply | null;

        if (reply?.fields !== undefined) {
          onRow(reply.fields, reply.updatedAt);
        }

        toast.error(__('This record was changed by someone else since you opened it.'));
      } else {
        const message = failure instanceof ApiError ? (failure.data as { message?: string } | null)?.message : undefined;
        toast.error(failure instanceof ApiError && failure.status === 403 && message !== undefined ? message : __('Something went wrong. Please try again.'));
      }
    } finally {
      setPending(null);
    }
  }

  function displayOf(value: unknown): string {
    const labels = new Map(options.map((option) => [option.value, option.label]));

    return Array.isArray(value) ? value.map((item) => labels.get(stringValue(item)) ?? stringValue(item)).join(', ') : (labels.get(stringValue(value)) ?? stringValue(value));
  }

  const describedBy = error === null ? undefined : errorId;

  // The grid asks for an edit from Enter, F2 or a double-click; a boolean flips at once.
  useEffect(() => {
    const element = root.current;
    const edit = () => {
      if (current.current.busy) {
        return;
      }

      if (column.component === 'boolean-field') {
        const on = current.current.shown.value === true || current.current.shown.value === column.trueValue;
        void current.current.commit(!on);
      } else {
        setEditing(true);
      }
    };

    element?.addEventListener('lathe-edit', edit);

    return () => {
      element?.removeEventListener('lathe-edit', edit);
    };
  }, [column]);

  // Leaving edit mode hands the focus back to the cell, or to the next one after Tab.
  function close(next = false) {
    setEditing(false);
    const cell = root.current?.closest('td');
    const target = next ? cell?.nextElementSibling : cell;

    if (target instanceof HTMLElement) {
      target.focus();
    }
  }

  return (
    <div ref={root} data-inline-cell="" className="relative">
      {editing ? (
        <Suspense fallback={<TableCell column={column} cell={shown} />}>
          <InlineEditor
            uriKey={uriKey}
            rowId={rowId}
            column={column}
            cell={shown}
            describedBy={describedBy}
            onCommit={(value, shownWhilePending, extra) => {
              void commit(value, shownWhilePending, extra);
            }}
            onClose={close}
          />
        </Suspense>
      ) : (
        <TableCell column={column} cell={shown} />
      )}
      {busy ? <span className="ms-2 text-xs text-muted-foreground">{__('Saving…')}</span> : null}
      {conflict === undefined ? null : (
        <Suspense fallback={null}>
          <ConflictDialog
            onRefresh={() => {
              setConflict(undefined);
            }}
            onOverride={() => {
              setConflict(undefined);
              void undo((conflict as { before: unknown }).before, latest.current.token);
            }}
            onClose={() => {
              setConflict(undefined);
            }}
          />
        </Suspense>
      )}
      {error === null ? null : (
        <div id={errorId} role="tooltip" className="absolute start-0 top-full z-10 mt-1 rounded-md bg-destructive px-2 py-1 text-xs whitespace-nowrap text-destructive-foreground shadow">
          {error}
        </div>
      )}
    </div>
  );
}
