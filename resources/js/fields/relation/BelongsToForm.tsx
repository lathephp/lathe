import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { Plus } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { inputClass } from '../choice/options';
import type { FormFieldProps } from '../registry';
import { isBelongsToValue } from './BelongsToField';
import { useAssociatable, type AssociatableItem, type LookupRecord } from './useAssociatable';

// The related create form loads only when a "+" is pressed, so no list page carries it.
const CreateRelationModal = lazy(() => import('./CreateRelationModal').then((module) => ({ default: module.CreateRelationModal })));

/** What a lookup needs beyond the field: a morphTo's chosen type, or the record of an index row. */
interface LookupProps {
  type?: string;
  record?: LookupRecord;
}

function picked(item: AssociatableItem) {
  return { value: item.value, display: item.display };
}

function WithTrashedBox({ checked, onChange }: { checked: boolean; onChange: (checked: boolean) => void }) {
  const { __ } = useTranslation();

  return (
    <label className="mt-1 flex items-center gap-2 text-sm">
      <input
        type="checkbox"
        checked={checked}
        onChange={(event) => {
          onChange(event.target.checked);
        }}
      />
      {__('With trashed')}
    </label>
  );
}

/** A button that opens a searchable list of the related records, loaded from the server only once opened. */
export function RelationPicker({ field, value, onChange, id, describedBy, invalid, disabled, type, record, endpoint }: FormFieldProps & LookupProps & { endpoint?: string }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const [term, setTerm] = useState('');
  const [withTrashed, setWithTrashed] = useState(false);
  const [active, setActive] = useState(-1);
  const { items, hasMore, loaded, error, more } = useAssociatable(field, { open, term, withTrashed, type, record, endpoint });
  const root = useRef<HTMLDivElement>(null);
  const button = useRef<HTMLButtonElement>(null);
  const search = useRef<HTMLInputElement>(null);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    search.current?.focus();

    function outside(event: MouseEvent) {
      if (event.target instanceof Node && root.current?.contains(event.target) !== true) {
        setOpen(false);
      }
    }

    document.addEventListener('mousedown', outside);

    return () => {
      document.removeEventListener('mousedown', outside);
    };
  }, [open]);

  function close() {
    setOpen(false);
    button.current?.focus();
  }

  function choose(item: AssociatableItem) {
    onChange(picked(item));
    close();
  }

  return (
    <div ref={root} className="relative">
      {/* The form moves focus to the first [aria-invalid] control after a failed submit; a button takes no aria-required, the label marks it. */}
      {/* eslint-disable-next-line jsx-a11y/role-supports-aria-props */}
      <button
        ref={button}
        id={id}
        type="button"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={open ? `${id}-listbox` : undefined}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        disabled={disabled}
        onClick={() => {
          setActive(-1);
          setOpen(!open);
        }}
        className={`${inputClass} text-left`}
      >
        {isBelongsToValue(value) ? value.display : <span className="text-muted-foreground">{field.placeholder ?? __('Select :title', { title: label })}</span>}
      </button>
      {open && (
        <div className="absolute z-20 mt-1 w-full rounded-md border border-border bg-background p-2 shadow-md">
          <input
            ref={search}
            type="search"
            aria-label={__('Search')}
            placeholder={__('Search')}
            aria-controls={`${id}-listbox`}
            aria-activedescendant={active >= 0 && active < items.length ? `${id}-option-${String(active)}` : undefined}
            value={term}
            // The server refuses a longer term; 100 is limits.search_term_chars by default.
            maxLength={typeof field.searchTermChars === 'number' ? field.searchTermChars : 100}
            onChange={(event) => {
              setTerm(event.target.value);
              setActive(-1);
            }}
            onKeyDown={(event) => {
              if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(Math.min(active + 1, items.length - 1));
              } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(Math.max(active - 1, 0));
              } else if (event.key === 'Enter') {
                // Enter picks the highlighted option and never submits the form.
                event.preventDefault();
                const item = items[active];

                if (item !== undefined) {
                  choose(item);
                }
              } else if (event.key === 'Escape') {
                event.preventDefault();
                close();
              }
            }}
            className={inputClass}
          />
          {field.withTrashed === true && <WithTrashedBox checked={withTrashed} onChange={setWithTrashed} />}
          <ul id={`${id}-listbox`} role="listbox" aria-label={label} className="mt-1 max-h-60 overflow-y-auto">
            {items.map((item, index) => (
              // Options never take focus: the search box drives them with aria-activedescendant, so the keys live there.
              // eslint-disable-next-line jsx-a11y/click-events-have-key-events
              <li
                key={item.value}
                id={`${id}-option-${String(index)}`}
                role="option"
                aria-selected={isBelongsToValue(value) && String(value.value) === String(item.value)}
                onMouseDown={(event) => {
                  // Keeps the focus in the search box until the click lands.
                  event.preventDefault();
                }}
                onClick={() => {
                  choose(item);
                }}
                className={`cursor-pointer rounded px-2 py-1 text-sm hover:bg-muted ${index === active ? 'bg-muted' : ''}`}
              >
                {item.display}
                {item.subtitle !== undefined && <span className="block text-xs text-muted-foreground">{item.subtitle}</span>}
              </li>
            ))}
          </ul>
          {error !== null && (
            <p role="alert" className="px-2 py-1 text-sm text-destructive">
              {error}
            </p>
          )}
          {loaded && items.length === 0 && <p className="px-2 py-1 text-sm text-muted-foreground">{__('No results')}</p>}
          {hasMore && (
            <Button variant="outline" size="sm" className="mt-1 w-full" onClick={more}>
              {__('More')}
            </Button>
          )}
        </div>
      )}
    </div>
  );
}

/** A native select whose options load on first focus; a first page with more turns it into the picker. */
function RelationSelect({ field, value, onChange, id, describedBy, invalid, disabled, type, record, onTooMany }: FormFieldProps & LookupProps & { onTooMany: () => void }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const [withTrashed, setWithTrashed] = useState(false);
  const { items, hasMore } = useAssociatable(field, { open, term: '', withTrashed, type, record });
  const current = isBelongsToValue(value) ? String(value.value) : '';
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;

  useEffect(() => {
    if (hasMore) {
      onTooMany();
    }
  }, [hasMore, onTooMany]);

  return (
    <div>
      <select
        id={id}
        name={field.attribute}
        value={current}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onFocus={() => {
          setOpen(true);
        }}
        onMouseDown={() => {
          setOpen(true);
        }}
        onChange={(event) => {
          const item = items.find((option) => String(option.value) === event.target.value);

          if (event.target.value === '') {
            onChange(null);
          } else if (item !== undefined) {
            onChange(picked(item));
          }
        }}
        className={inputClass}
      >
        <option value="">{field.placeholder ?? __('Select :title', { title: label })}</option>
        {isBelongsToValue(value) && !items.some((item) => String(item.value) === current) && <option value={current}>{value.display}</option>}
        {items.map((item) => (
          <option key={item.value} value={String(item.value)}>
            {item.display}
          </option>
        ))}
      </select>
      {field.withTrashed === true && <WithTrashedBox checked={withTrashed} onChange={setWithTrashed} />}
    </div>
  );
}

export function BelongsToForm(props: FormFieldProps & LookupProps) {
  const { __ } = useTranslation();
  const { field, value, onChange, disabled } = props;
  const [searchable, setSearchable] = useState(field.searchable === true);
  const [creating, setCreating] = useState(false);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;

  // A field locked by the page, such as under a via, shows its record and no picker.
  if (field.readonly === true) {
    return <span id={props.id}>{isBelongsToValue(value) ? value.display : '—'}</span>;
  }

  const control = searchable ? (
    <RelationPicker {...props} />
  ) : (
    <RelationSelect
      {...props}
      onTooMany={() => {
        setSearchable(true);
      }}
    />
  );

  if (field.creatable !== true) {
    return control;
  }

  return (
    <div className="flex items-start gap-2">
      <div className="min-w-0 flex-1">{control}</div>
      <Button
        variant="outline"
        size="icon"
        aria-label={__('Create :resource', { resource: label })}
        disabled={disabled}
        onClick={() => {
          setCreating(true);
        }}
      >
        <Plus aria-hidden="true" className="size-4" />
      </Button>
      {creating && (
        <Suspense fallback={null}>
          <CreateRelationModal
            field={field}
            record={props.record}
            onCreated={onChange}
            onClose={() => {
              setCreating(false);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
