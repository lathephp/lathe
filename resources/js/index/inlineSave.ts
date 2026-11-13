import { ApiError, request, toast, type useTranslation } from "@lathephp/core";
import type { Cell, FieldSchema } from "../fields/types";
import { lastToken, pushUndo, saved } from "./undo";

// The save half of an inline cell. It loads with the cell's editor, so a list's first paint
// carries only the values.

export interface InlineReply {
  fields: Record<string, Cell>;
  updatedAt?: string | null;
}

/** The record and the field a save patches. */
export interface FieldTarget {
  apiPath: string;
  uriKey: string;
  rowId: string | number;
  key: string;
}

/** PATCHes one field against the record's stale token and keeps the token the save returns, which an undo sends. */
export async function patchField(
  target: FieldTarget,
  value: unknown,
  updatedAt: string | null | undefined,
  extra: Record<string, unknown> = {},
): Promise<InlineReply | null> {
  const reply = await request<InlineReply>(
    `${target.apiPath}/resources/${target.uriKey}/${String(target.rowId)}/field/${target.key}`,
    {
      method: "PATCH",
      toastOnError: false,
      body: { value, ...extra, _updated_at: updatedAt ?? undefined },
    },
  );

  if (reply !== null) {
    saved(`${target.uriKey}/${String(target.rowId)}`, reply.updatedAt);
  }

  return reply;
}

/** Why the server refused a patch, for a toast: the value's first error, the denial, or a stale record. */
export function refusalOf(
  failure: unknown,
  __: ReturnType<typeof useTranslation>["__"],
): string {
  const data =
    failure instanceof ApiError
      ? (failure.data as {
          message?: string;
          errors?: Record<string, string[]>;
        } | null)
      : null;

  if (failure instanceof ApiError && failure.status === 422) {
    return (
      Object.values(data?.errors ?? {})[0]?.[0] ??
      __("Please fix the highlighted fields.")
    );
  }

  if (failure instanceof ApiError && failure.status === 409) {
    return __("This record was changed by someone else since you opened it.");
  }

  return failure instanceof ApiError &&
    failure.status === 403 &&
    data?.message !== undefined
    ? data.message
    : __("Something went wrong. Please try again.");
}

/** What a save reads from the cell and the state it sets there. */
export interface InlineSave {
  apiPath: string;
  uriKey: string;
  rowId: string | number;
  column: FieldSchema;
  /** The cell's value before this save. */
  cell: Cell;
  __: ReturnType<typeof useTranslation>["__"];
  displayOf: (value: unknown) => string;
  /** The row callback of the latest render; an undo runs long after the save. */
  onRow: {
    current: (
      fields: Record<string, Cell>,
      updatedAt: string | null | undefined,
    ) => void;
  };
  setPending: (cell: Cell | null) => void;
  setError: (error: string | null) => void;
  setDraft: (cell: Cell | null) => void;
  setEditing: (editing: boolean) => void;
  setConflict: (conflict: {
    value: unknown;
    extra: Record<string, unknown>;
    undoing: boolean;
  }) => void;
}

// Each record's inline saves, from any list, lens or relation table of the page, go one after
// another. Every token a save of the record sent and the one it got back, by record: a save
// sends the token its row showed, moved on through the saves this tab made since, so a second
// edit sent before the first one's reply is no conflict with this tab's own save.
const queues = new Map<string, Promise<unknown>>();
const passed = new Map<
  string,
  Map<string | null | undefined, string | null | undefined>
>();

/** Forgets every record's queue and tokens, as for a test that starts a page afresh. */
export function forgetSaves(): void {
  queues.clear();
  passed.clear();
}

/** The token a row that showed `shown` holds after this tab's saves of the record. */
function moved(
  key: string,
  shown: string | null | undefined,
): string | null | undefined {
  const chain = passed.get(key);
  const seen = new Set<string | null | undefined>();
  let token = shown;

  while (chain?.has(token) === true && !seen.has(token)) {
    seen.add(token);
    token = chain.get(token);
  }

  return token;
}

function send(
  save: InlineSave,
  value: unknown,
  extra: Record<string, unknown>,
  updatedAt: string | null | undefined,
) {
  const key = record(save);
  const turn = async () => {
    const token = moved(key, updatedAt);
    const reply = await patchField(
      {
        apiPath: save.apiPath,
        uriKey: save.uriKey,
        rowId: save.rowId,
        key: save.column.key,
      },
      value,
      token,
      extra,
    );

    if (
      reply !== null &&
      reply.updatedAt !== undefined &&
      reply.updatedAt !== token
    ) {
      const chain =
        passed.get(key) ??
        new Map<string | null | undefined, string | null | undefined>();
      chain.set(token, reply.updatedAt);
      passed.set(key, chain);
    }

    return reply;
  };
  // The next save waits for this one to settle, whether it passed or was refused.
  const done = (queues.get(key) ?? Promise.resolve()).then(turn, turn);
  // Once the last queued save has settled, every row shows its newest token: the chain goes.
  const settled: Promise<unknown> = done
    .catch(() => undefined)
    .then(() => {
      if (queues.get(key) === settled) {
        queues.delete(key);
        passed.delete(key);
      }
    });
  queues.set(key, settled);

  return done;
}

function record(save: InlineSave): string {
  return `${save.uriKey}/${String(save.rowId)}`;
}

/** The token this tab's last save of the cell's record returned, which an override saves against. */
export function tokenOf(save: InlineSave): string | null | undefined {
  return lastToken(record(save));
}

// A save someone else's change refused: the row takes their cells and the dialog asks.
function refused(
  save: InlineSave,
  failure: unknown,
  value: unknown,
  extra: Record<string, unknown>,
  undoing: boolean,
): boolean {
  const reply =
    failure instanceof ApiError && failure.status === 409
      ? (failure.data as InlineReply | null)
      : null;

  if (reply?.fields === undefined) {
    return false;
  }

  save.onRow.current(reply.fields, reply.updatedAt);
  saved(record(save), reply.updatedAt);
  save.setConflict({ value, extra, undoing });

  return true;
}

/** Saves the value the cell had before as a new change, against the token this tab's last save of the record returned. */
export async function undo(
  save: InlineSave,
  before: unknown,
  extra: Record<string, unknown> = {},
  shownBefore?: Cell,
) {
  const { __ } = save;
  save.setPending(
    shownBefore ?? { value: before, display: save.displayOf(before) },
  );

  try {
    const reply = await send(save, before, extra, lastToken(record(save)));

    if (reply !== null) {
      save.onRow.current(reply.fields, reply.updatedAt);
    }

    toast.success(__("Change undone"));
  } catch (failure) {
    if (!refused(save, failure, before, extra, true)) {
      toast.error(__("Something went wrong. Please try again."));
    }
  } finally {
    save.setPending(null);
  }
}

/** Sends a value the cell already shows as pending, then offers its undo or shows why it was refused. */
export async function commit(
  save: InlineSave,
  value: unknown,
  shownWhilePending: Cell | undefined,
  extra: Record<string, unknown>,
  updatedAt: string | null | undefined,
) {
  const { __, cell, column } = save;

  try {
    const reply = await send(save, value, extra, updatedAt);

    if (reply !== null) {
      save.onRow.current(reply.fields, reply.updatedAt);

      // A belongsTo or morphTo cell holds the related record; its undo sends that record's id, and type.
      const old = cell.value as {
        value?: unknown;
        id?: unknown;
        type?: unknown;
      } | null;
      const before =
        column.component === "belongs-to-field"
          ? (old?.value ?? null)
          : column.component === "morph-to-field"
            ? (old?.id ?? null)
            : cell.value;
      const restore =
        column.component === "morph-to-field"
          ? { type: old?.type ?? null }
          : {};
      const display =
        reply.fields[column.key]?.display ?? save.displayOf(value);
      const take = pushUndo(() => undo(save, before, restore, cell));

      toast.success(
        __(":field changed to :value.", {
          field: column.label,
          value: display,
        }),
        {
          action: { label: __("Undo"), onClick: take },
        },
      );
    }
  } catch (failure) {
    if (failure instanceof ApiError && failure.status === 422) {
      // The refused value stays in the editor, marked with the server's message.
      const errors =
        (failure.data as { errors?: Record<string, string[]> } | null)
          ?.errors ?? {};
      save.setError(
        Object.values(errors)[0]?.[0] ??
          __("Please fix the highlighted fields."),
      );
      // A boolean has no editor to reopen; its message shows on the cell.
      if (column.component !== "boolean-field") {
        // A select comes back holding the old value, so its list does not cover the message.
        if (
          column.component !== "select-field" &&
          column.component !== "multi-select-field"
        ) {
          save.setDraft(
            shownWhilePending ?? { value, display: save.displayOf(value) },
          );
        }

        save.setEditing(true);
      }
    } else if (!refused(save, failure, value, extra, false)) {
      toast.error(refusalOf(failure, __));
    }
  } finally {
    save.setPending(null);
  }
}
