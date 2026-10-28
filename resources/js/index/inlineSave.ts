import { ApiError, request, toast, type useTranslation } from '@lathephp/core';
import type { Cell, FieldSchema } from '../fields/types';
import { lastToken, pushUndo, saved } from './undo';

// The save half of an inline cell. It loads with the cell's editor, so a list's first paint
// carries only the values.

interface InlineReply {
  fields: Record<string, Cell>;
  updatedAt?: string | null;
}

/** What a save reads from the cell and the state it sets there. */
export interface InlineSave {
  apiPath: string;
  uriKey: string;
  rowId: string | number;
  column: FieldSchema;
  /** The cell's value before this save. */
  cell: Cell;
  __: ReturnType<typeof useTranslation>['__'];
  displayOf: (value: unknown) => string;
  /** The row callback of the latest render; an undo runs long after the save. */
  onRow: { current: (fields: Record<string, Cell>, updatedAt: string | null | undefined) => void };
  setPending: (cell: Cell | null) => void;
  setError: (error: string | null) => void;
  setDraft: (cell: Cell | null) => void;
  setEditing: (editing: boolean) => void;
  setConflict: (conflict: { value: unknown; extra: Record<string, unknown>; undoing: boolean }) => void;
}

function send(save: InlineSave, value: unknown, extra: Record<string, unknown>, updatedAt: string | null | undefined) {
  return request<InlineReply>(`${save.apiPath}/resources/${save.uriKey}/${String(save.rowId)}/field/${save.column.key}`, {
    method: 'PATCH',
    toastOnError: false,
    body: { value, ...extra, _updated_at: updatedAt ?? undefined },
  });
}

function record(save: InlineSave): string {
  return `${save.uriKey}/${String(save.rowId)}`;
}

/** The token this tab's last save of the cell's record returned, which an override saves against. */
export function tokenOf(save: InlineSave): string | null | undefined {
  return lastToken(record(save));
}

// A save someone else's change refused: the row takes their cells and the dialog asks.
function refused(save: InlineSave, failure: unknown, value: unknown, extra: Record<string, unknown>, undoing: boolean): boolean {
  const reply = failure instanceof ApiError && failure.status === 409 ? (failure.data as InlineReply | null) : null;

  if (reply?.fields === undefined) {
    return false;
  }

  save.onRow.current(reply.fields, reply.updatedAt);
  saved(record(save), reply.updatedAt);
  save.setConflict({ value, extra, undoing });

  return true;
}

/** Saves the value the cell had before as a new change, against the token this tab's last save of the record returned. */
export async function undo(save: InlineSave, before: unknown, extra: Record<string, unknown> = {}, shownBefore?: Cell) {
  const { __ } = save;
  save.setPending(shownBefore ?? { value: before, display: save.displayOf(before) });

  try {
    const reply = await send(save, before, extra, lastToken(record(save)));

    if (reply !== null) {
      save.onRow.current(reply.fields, reply.updatedAt);
      saved(record(save), reply.updatedAt);
    }

    toast.success(__('Change undone'));
  } catch (failure) {
    if (!refused(save, failure, before, extra, true)) {
      toast.error(__('Something went wrong. Please try again.'));
    }
  } finally {
    save.setPending(null);
  }
}

/** Sends a value the cell already shows as pending, then offers its undo or shows why it was refused. */
export async function commit(save: InlineSave, value: unknown, shownWhilePending: Cell | undefined, extra: Record<string, unknown>, updatedAt: string | null | undefined) {
  const { __, cell, column } = save;

  try {
    const reply = await send(save, value, extra, updatedAt);

    if (reply !== null) {
      save.onRow.current(reply.fields, reply.updatedAt);
      saved(record(save), reply.updatedAt);

      // A belongsTo or morphTo cell holds the related record; its undo sends that record's id, and type.
      const old = cell.value as { value?: unknown; id?: unknown; type?: unknown } | null;
      const before = column.component === 'belongs-to-field' ? (old?.value ?? null) : column.component === 'morph-to-field' ? (old?.id ?? null) : cell.value;
      const restore = column.component === 'morph-to-field' ? { type: old?.type ?? null } : {};
      const display = reply.fields[column.key]?.display ?? save.displayOf(value);
      const take = pushUndo(() => undo(save, before, restore, cell));

      toast.success(__(':field changed to :value.', { field: column.label, value: display }), {
        action: { label: __('Undo'), onClick: take },
      });
    }
  } catch (failure) {
    if (failure instanceof ApiError && failure.status === 422) {
      // The refused value stays in the editor, marked with the server's message.
      const errors = (failure.data as { errors?: Record<string, string[]> } | null)?.errors ?? {};
      save.setError(Object.values(errors)[0]?.[0] ?? __('Please fix the highlighted fields.'));
      // A boolean has no editor to reopen; its message shows on the cell.
      if (column.component !== 'boolean-field') {
        save.setDraft(shownWhilePending ?? { value, display: save.displayOf(value) });
        save.setEditing(true);
      }
    } else if (!refused(save, failure, value, extra, false)) {
      const message = failure instanceof ApiError ? (failure.data as { message?: string } | null)?.message : undefined;
      toast.error(failure instanceof ApiError && failure.status === 403 && message !== undefined ? message : __('Something went wrong. Please try again.'));
    }
  } finally {
    save.setPending(null);
  }
}
