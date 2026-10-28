import { Suspense, useRef, useState, type KeyboardEvent } from 'react';
import { SelectMenu } from '../components/ui/SelectMenu';
import { SelectBadge, styled } from '../fields/choice/SelectFields';
import type { Cell, FieldSchema } from '../fields/types';
import { stringValue } from '../fields/registry';
import { isBelongsToValue } from '../fields/relation/BelongsToField';
import { BelongsToForm, MorphToForm } from '../fields/relation/lazy';
import { isMorphToValue } from '../fields/relation/MorphToField';

interface Option {
  value: string;
  label: string;
}

export interface InlineEditorProps {
  uriKey: string;
  rowId: string | number;
  column: FieldSchema;
  cell: Cell;
  /** The id of the server's message under the cell, while there is one. */
  describedBy: string | undefined;
  /** Whether the server refused the value the editor holds. */
  invalid: boolean;
  /** Saves a value; the cell shows `shown` until the reply. */
  onCommit: (value: unknown, shown?: Cell, extra?: Record<string, unknown>) => void;
  /** Leaves edit mode; `next` moves the cursor one cell on, as Tab does. */
  onClose: (next?: boolean) => void;
}

/**
 * The control of a cell in edit mode, loaded on the first edit: a select opens its list at once,
 * a text or number input saves on Enter or Tab, a belongsTo or morphTo picker saves on the record
 * picked. Escape leaves without saving.
 */
export function InlineEditor({ uriKey, rowId, column, cell, describedBy, invalid, onCommit, onClose }: InlineEditorProps) {
  const [text, setText] = useState(stringValue(cell.value));
  const [many, setMany] = useState<string[]>(Array.isArray(cell.value) ? cell.value.map(stringValue) : []);
  // A morphTo type chosen but no record picked yet; undefined while there is none.
  const [morphDraft, setMorphDraft] = useState<unknown>(undefined);
  // Enter, Tab or Escape ends a text edit; the blur as the input leaves the page must not save again.
  const done = useRef(false);
  const options = (column.options as Option[] | undefined) ?? [];
  const common = { 'aria-label': column.label, 'aria-describedby': describedBy, 'aria-invalid': invalid || undefined, size: 'sm' as const, className: 'max-w-full' };

  // Escape in a picker leaves the cell as it was.
  function escape(event: KeyboardEvent) {
    if (event.key === 'Escape') {
      event.preventDefault();
      onClose();
    }
  }

  if (column.component === 'multi-select-field') {
    const before = Array.isArray(cell.value) ? cell.value.map(stringValue) : [];

    return (
      // The list is portaled, but its keys still pass here through React; Escape marks a cancel.
      <div
        onKeyDownCapture={(event) => {
          done.current ||= event.key === 'Escape';
        }}
      >
        <SelectMenu
          multiple
          defaultOpen
          {...common}
          value={many}
          options={options}
          onChange={setMany}
          // The choices save together as the list closes, unless Escape closed it.
          onOpenChange={(open) => {
            if (!open) {
              if (!done.current && many.join() !== before.join()) {
                onCommit(many);
              }

              onClose();
            }
          }}
        />
      </div>
    );
  }

  if (column.component === 'select-field') {
    return (
      <SelectMenu
        defaultOpen
        {...common}
        {...(!styled(column) ? {} : { display: (value: string) => <SelectBadge field={column} value={value} /> })}
        value={stringValue(cell.value)}
        // A column that cannot hold null offers no blank choice.
        options={column.nullable ? [{ value: '', label: '—' }, ...options] : options}
        onChange={(value) => {
          onCommit(value);
          onClose();
        }}
        onOpenChange={(open) => {
          if (!open) {
            onClose();
          }
        }}
      />
    );
  }

  if (column.component === 'belongs-to-field' || column.component === 'morph-to-field') {
    const picker = { id: `${String(rowId)}-${column.key}-picker`, describedBy, invalid, disabled: false, record: { uriKey, id: rowId } };

    return (
      // eslint-disable-next-line jsx-a11y/no-static-element-interactions -- Escape from the picker inside
      <div onKeyDown={escape}>
        <label htmlFor={picker.id} className="sr-only">
          {column.label}
        </label>
        <Suspense fallback={<div className="h-8" aria-hidden="true" />}>
          {column.component === 'belongs-to-field' ? (
            <BelongsToForm
              {...picker}
              field={{ ...column, value: cell.value }}
              value={cell.value}
              onChange={(picked) => {
                onCommit(isBelongsToValue(picked) ? picked.value : null, { value: picked, display: isBelongsToValue(picked) ? picked.display : '' });
                onClose();
              }}
            />
          ) : (
            <MorphToForm
              {...picker}
              field={{ ...column, value: cell.value }}
              value={morphDraft === undefined ? cell.value : morphDraft}
              onChange={(picked) => {
                // A new type alone saves nothing: the cell waits for a record of that type.
                if (isMorphToValue(picked) && picked.id !== null) {
                  onCommit(picked.id, { value: picked, display: picked.display ?? '' }, { type: picked.type });
                  onClose();
                } else {
                  setMorphDraft(picked);
                }
              }}
            />
          )}
        </Suspense>
      </div>
    );
  }

  const numeric = column.component === 'number-field' || column.component === 'currency-field';
  function finish(save: boolean, next = false) {
    if (done.current) {
      return;
    }

    done.current = true;

    if (save && text !== stringValue(cell.value)) {
      onCommit(text);
    }

    onClose(next);
  }

  return (
    <input
      // The edit opened from the keys or a double-click, so the input takes the focus at once.
      // eslint-disable-next-line jsx-a11y/no-autofocus
      autoFocus
      aria-label={column.label}
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      type={numeric ? 'number' : 'text'}
      value={text}
      onChange={(event) => {
        setText(event.target.value);
      }}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === 'Tab' || event.key === 'Escape') {
          event.preventDefault();
          finish(event.key !== 'Escape', event.key === 'Tab');
        }
      }}
      onBlur={() => {
        finish(true);
      }}
      className="h-8 w-full rounded-md border border-input bg-background px-2 text-sm"
    />
  );
}
