import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { Cell, FieldSchema } from '../fields/types';
import { stringValue } from '../fields/registry';
import { lazyNamed } from '../lib/lazy';
import { TableCell } from '../table/cells';
import { ConflictDialog } from './lazy';
import { lastToken, pushUndo, saved } from './undo';

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
  // An undo outlives this render: the stack calls it later, so it reads the latest row callback from a ref.
  const latest = useRef(onRow);
  useEffect(() => {
    latest.current = onRow;
  });
  // A save someone else's change refused: the value to save over theirs, and whether it was an undo.
  const [conflict, setConflict] = useState<{ value: unknown; extra: Record<string, unknown>; undoing: boolean } | null>(null);
  // A value the server refused, kept in the reopened editor for fixing.
  const [draft, setDraft] = useState<Cell | null>(null);
  const shown = pending ?? cell;
  const busy = pending !== null;
  // What the grid's edit request reads when it comes, after this render.
  const current = useRef({ busy, shown, commit });
  useEffect(() => {
    current.current = { busy, shown, commit };
  });
  const options = (column.options as Option[] | undefined) ?? [];
  const record = `${uriKey}/${String(rowId)}`;

  function send(value: unknown, extra: Record<string, unknown>, updatedAt: string | null | undefined) {
    return request<InlineReply>(`${apiPath}/resources/${uriKey}/${String(rowId)}/field/${column.key}`, {
      method: 'PATCH',
      toastOnError: false,
      body: { value, ...extra, _updated_at: updatedAt ?? undefined },
    });
  }

  // A save someone else's change refused: the row takes their cells and the dialog asks.
  function refused(failure: unknown, value: unknown, extra: Record<string, unknown>, undoing: boolean): boolean {
    const reply = failure instanceof ApiError && failure.status === 409 ? (failure.data as InlineReply | null) : null;

    if (reply?.fields === undefined) {
      return false;
    }

    latest.current(reply.fields, reply.updatedAt);
    saved(record, reply.updatedAt);
    setConflict({ value, extra, undoing });

    return true;
  }

  /** Saves the value the cell had before as a new change, against the token this tab's last save of the record returned. */
  async function undo(before: unknown, extra: Record<string, unknown> = {}, shownBefore?: Cell) {
    setPending(shownBefore ?? { value: before, display: displayOf(before) });

    try {
      const reply = await send(before, extra, lastToken(record));

      if (reply !== null) {
        latest.current(reply.fields, reply.updatedAt);
        saved(record, reply.updatedAt);
      }

      toast.success(__('Change undone'));
    } catch (failure) {
      if (!refused(failure, before, extra, true)) {
        toast.error(__('Something went wrong. Please try again.'));
      }
    } finally {
      setPending(null);
    }
  }

  async function commit(value: unknown, shownWhilePending?: Cell, extra: Record<string, unknown> = {}, updatedAt = token) {
    if (busy) {
      return;
    }

    setError(null);
    setDraft(null);
    setPending(shownWhilePending ?? { value, display: displayOf(value) });

    try {
      const reply = await send(value, extra, updatedAt);

      if (reply !== null) {
        onRow(reply.fields, reply.updatedAt);
        saved(record, reply.updatedAt);

        // A belongsTo or morphTo cell holds the related record; its undo sends that record's id, and type.
        const old = cell.value as { value?: unknown; id?: unknown; type?: unknown } | null;
        const before = column.component === 'belongs-to-field' ? (old?.value ?? null) : column.component === 'morph-to-field' ? (old?.id ?? null) : cell.value;
        const restore = column.component === 'morph-to-field' ? { type: old?.type ?? null } : {};
        const display = reply.fields[column.key]?.display ?? displayOf(value);
        const shownBefore = cell;
        const take = pushUndo(() => undo(before, restore, shownBefore));

        toast.success(__(':field changed to :value.', { field: column.label, value: display }), {
          action: { label: __('Undo'), onClick: take },
        });
      }
    } catch (failure) {
      if (failure instanceof ApiError && failure.status === 422) {
        // The refused value stays in the editor, marked with the server's message.
        const errors = (failure.data as { errors?: Record<string, string[]> } | null)?.errors ?? {};
        setError(Object.values(errors)[0]?.[0] ?? __('Please fix the highlighted fields.'));
        // A boolean has no editor to reopen; its message shows on the cell.
        if (column.component !== 'boolean-field') {
          setDraft(shownWhilePending ?? { value, display: displayOf(value) });
          setEditing(true);
        }
      } else if (!refused(failure, value, extra, false)) {
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
    setError(null);
    setDraft(null);
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
            cell={draft ?? shown}
            describedBy={describedBy}
            invalid={error !== null}
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
      {/* On the page, not in the cell: a sticky cell would hold the dialog in its own box. */}
      {conflict === null
        ? null
        : createPortal(
            <Suspense fallback={null}>
              <ConflictDialog
                onRefresh={() => {
                  setConflict(null);
                }}
                onOverride={() => {
                  setConflict(null);

                  if (conflict.undoing) {
                    void undo(conflict.value, conflict.extra);
                  } else {
                    void commit(conflict.value, undefined, conflict.extra, lastToken(record));
                  }
                }}
                onClose={() => {
                  setConflict(null);
                }}
              />
            </Suspense>,
            document.body,
          )}
      {error === null ? null : (
        <div id={errorId} role="tooltip" className="absolute start-0 top-full z-10 mt-1 rounded-md bg-destructive px-2 py-1 text-xs whitespace-nowrap text-destructive-foreground shadow">
          {error}
        </div>
      )}
    </div>
  );
}
