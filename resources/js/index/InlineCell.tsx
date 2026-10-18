import { ApiError, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Suspense, useId, useState, type KeyboardEvent } from 'react';
import { Select } from '../components/ui/Select';
import type { Cell, FieldSchema } from '../fields/types';
import { stringValue } from '../fields/registry';
import { isBelongsToValue } from '../fields/relation/BelongsToField';
import { BelongsToForm, MorphToForm } from '../fields/relation/lazy';
import { isMorphToValue } from '../fields/relation/MorphToField';
import { cn } from '../lib/cn';
import { TableCell } from '../table/cells';


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
 * An index cell that saves in place: a boolean toggles at once, a select saves on choice,
 * a text or number input saves on Enter or blur and Escape reverts, a belongsTo or morphTo
 * picker saves on the record picked. One request at a time;
 * a refused value shows its message on the cell and the old value comes back.
 */
export function InlineCell({ uriKey, rowId, column, cell, token, onRow }: InlineCellProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const errorId = useId();
  const pickerId = useId();
  const [pending, setPending] = useState<Cell | null>(null);
  const [editing, setEditing] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  // A morphTo type chosen but no record picked yet; undefined while there is none.
  const [morphDraft, setMorphDraft] = useState<unknown>(undefined);
  const shown = pending ?? cell;
  const busy = pending !== null;
  const options = (column.options as Option[] | undefined) ?? [];

  async function commit(value: unknown, shownWhilePending?: Cell, extra: Record<string, unknown> = {}) {
    if (busy) {
      return;
    }

    setError(null);
    setPending(shownWhilePending ?? { value, display: displayOf(value) });

    try {
      const reply = await request<InlineReply>(`${apiPath}/resources/${uriKey}/${String(rowId)}/field/${column.key}`, {
        method: 'PATCH',
        toastOnError: false,
        body: { value, ...extra, _updated_at: token ?? undefined },
      });

      if (reply !== null) {
        onRow(reply.fields, reply.updatedAt);
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
  let control;

  if (column.component === 'boolean-field') {
    const on = shown.value === true || shown.value === column.trueValue;
    const common = {
      'aria-label': column.label,
      'aria-describedby': describedBy,
      disabled: busy,
    };

    control =
      column.asCheckbox === true ? (
        <input
          type="checkbox"
          checked={on}
          onChange={() => {
            void commit(!on);
          }}
          className="size-4"
          {...common}
        />
      ) : (
        <button
          type="button"
          role="switch"
          aria-checked={on}
          onClick={() => {
            void commit(!on);
          }}
          className={cn('relative h-5 w-9 rounded-full transition-colors', on ? 'bg-primary' : 'bg-muted')}
          {...common}
        >
          <span className={cn('absolute top-0.5 size-4 rounded-full bg-background transition-all', on ? 'start-4.5' : 'start-0.5')} />
        </button>
      );
  } else if (column.component === 'select-field' || column.component === 'multi-select-field') {
    const multiple = column.component === 'multi-select-field';
    const current = Array.isArray(shown.value) ? shown.value.map(stringValue) : stringValue(shown.value);

    const common = {
      'aria-label': column.label,
      'aria-describedby': describedBy,
      'aria-invalid': error !== null || undefined,
      disabled: busy,
      size: 'sm' as const,
      className: 'max-w-full',
    };

    control = multiple ? (
      <Select
        multiple
        {...common}
        value={Array.isArray(current) ? current : []}
        options={options}
        onChange={(values) => {
          void commit(values);
        }}
      />
    ) : (
      <Select
        {...common}
        value={typeof current === 'string' ? current : ''}
        options={[{ value: '', label: '—' }, ...options]}
        onChange={(value) => {
          void commit(value);
        }}
      />
    );
  } else if (column.component === 'belongs-to-field' || column.component === 'morph-to-field') {
    const common = { id: pickerId, describedBy, invalid: error !== null, disabled: busy, record: { uriKey, id: rowId } };

    control = (
      <>
        <label htmlFor={pickerId} className="sr-only">
          {column.label}
        </label>
        <Suspense fallback={<div className="h-8" aria-hidden="true" />}>
        {column.component === 'belongs-to-field' ? (
          <BelongsToForm
            {...common}
            field={{ ...column, value: shown.value }}
            value={shown.value}
            onChange={(picked) => {
              void commit(isBelongsToValue(picked) ? picked.value : null, { value: picked, display: isBelongsToValue(picked) ? picked.display : '' });
            }}
          />
        ) : (
          <MorphToForm
            {...common}
            field={{ ...column, value: shown.value }}
            value={morphDraft === undefined ? shown.value : morphDraft}
            onChange={(picked) => {
              // A new type alone saves nothing: the cell waits for a record of that type.
              if (isMorphToValue(picked) && picked.id !== null) {
                setMorphDraft(undefined);
                void commit(picked.id, { value: picked, display: picked.display ?? '' }, { type: picked.type });
              } else {
                setMorphDraft(picked);
              }
            }}
          />
        )}
        </Suspense>
      </>
    );
  } else if (editing !== null) {
    const numeric = column.component === 'number-field' || column.component === 'currency-field';

    control = (
      <input
        // A cell the person clicked to edit takes the focus at once.
        // eslint-disable-next-line jsx-a11y/no-autofocus
        autoFocus
        aria-label={column.label}
        aria-describedby={describedBy}
        aria-invalid={error !== null || undefined}
        type={numeric ? 'number' : 'text'}
        value={editing}
        disabled={busy}
        onChange={(event) => {
          setEditing(event.target.value);
        }}
        onKeyDown={(event: KeyboardEvent<HTMLInputElement>) => {
          if (event.key === 'Enter') {
            event.preventDefault();
            const value = editing;
            setEditing(null);
            void commit(value);
          } else if (event.key === 'Escape') {
            event.preventDefault();
            setEditing(null);
            setError(null);
          }
        }}
        onBlur={() => {
          if (editing !== stringValue(cell.value)) {
            const value = editing;
            setEditing(null);
            void commit(value);
          } else {
            setEditing(null);
          }
        }}
        className="h-8 w-full rounded-md border border-input bg-background px-2 text-sm"
      />
    );
  } else {
    control = (
      <button
        type="button"
        aria-label={__('Edit :field', { field: column.label })}
        aria-describedby={describedBy}
        className="w-full cursor-text truncate text-start"
        onClick={() => {
          setEditing(stringValue(cell.value));
        }}
      >
        <TableCell column={column} cell={shown} />
      </button>
    );
  }

  return (
    <div data-inline-cell="" className="relative">
      {control}
      {error === null ? null : (
        <div id={errorId} role="tooltip" className="absolute start-0 top-full z-10 mt-1 rounded-md bg-destructive px-2 py-1 text-xs whitespace-nowrap text-white shadow">
          {error}
        </div>
      )}
    </div>
  );
}
