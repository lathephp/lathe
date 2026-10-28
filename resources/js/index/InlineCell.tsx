import { useLathe, useTranslation } from '@lathephp/core';
import { Suspense, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { Cell, FieldSchema } from '../fields/types';
import { stringValue } from '../fields/registry';
import { lazyNamed } from '../lib/lazy';
import { TableCell } from '../table/cells';
import { ConflictDialog } from './lazy';
import type { InlineSave } from './inlineSave';

// The controls and the save load on the first edit, so a list's first paint carries only the values.
const editor = () => import('./InlineEditor');
const InlineEditor = lazyNamed(() => editor().then((module) => module.InlineEditor));

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

  // The save code loads with the editor; the cell shows the pending value meanwhile.
  function save(): InlineSave {
    return { apiPath, uriKey, rowId, column, cell, __, displayOf, onRow: latest, setPending, setError, setDraft, setEditing, setConflict };
  }

  function undo(before: unknown, extra: Record<string, unknown>) {
    void editor().then((module) => module.undo(save(), before, extra));
  }

  async function commit(value: unknown, shownWhilePending?: Cell, extra: Record<string, unknown> = {}, updatedAt = token) {
    if (busy) {
      return;
    }

    setError(null);
    setDraft(null);
    setPending(shownWhilePending ?? { value, display: displayOf(value) });
    const module = await editor();
    await module.commit(save(), value, shownWhilePending, extra, updatedAt);
  }

  function displayOf(value: unknown): string {
    const labels = new Map(options.map((option) => [option.value, option.label]));

    return Array.isArray(value) ? value.map((item) => labels.get(stringValue(item)) ?? stringValue(item)).join(', ') : (labels.get(stringValue(value)) ?? stringValue(value));
  }

  const describedBy = error === null ? undefined : errorId;

  // The grid asks for an edit from Enter, F2 or a double-click; a boolean flips at once. The editor's
  // code loads after the rows paint, so the keys typed right after Enter reach its input.
  useEffect(() => {
    const element = root.current;
    void InlineEditor.preload();
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
                    undo(conflict.value, conflict.extra);
                  } else {
                    void editor().then((module) => commit(conflict.value, undefined, conflict.extra, module.tokenOf(save())));
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
