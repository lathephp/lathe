import { useTranslation } from '@lathephp/core';
import { Button, Checkbox } from '@lathephp/ui';
import { Combobox, ComboboxContent, ComboboxInput, ComboboxItem, ComboboxList } from '@lathephp/ui/combobox';
import { Plus } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { Select } from '../../components/ui/Select';
import { composing } from '../../lib/keys';
import type { FormFieldProps } from '../registry';
import { isBelongsToValue } from './BelongsToField';
import { useAssociatable, type AssociatableItem, type LookupRecord } from './useAssociatable';

// The related create form loads only when a create is chosen, so no list page carries it.
const CreateRelationSheet = lazy(() => import('./CreateRelationSheet').then((module) => ({ default: module.CreateRelationSheet })));

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
      <Checkbox checked={checked} onCheckedChange={onChange} />
      {__('With trashed')}
    </label>
  );
}

/** The typed text offered as a new record, after the matches. */
interface CreateOption {
  create: string;
}

type PickerOption = AssociatableItem | CreateOption;

const isCreate = (option: PickerOption): option is CreateOption => 'create' in option;

/** A combobox that searches the related records, loaded from the server only once opened. */
export function RelationPicker({
  field,
  value,
  onChange,
  id,
  describedBy,
  invalid,
  disabled,
  type,
  record,
  endpoint,
  onCreate,
  startOpen = false,
  takeFocus = false,
}: FormFieldProps & LookupProps & { endpoint?: string; onCreate?: (text: string) => void; startOpen?: boolean; takeFocus?: boolean }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(startOpen);
  // The typed search, or null while the input shows the chosen record.
  const [term, setTerm] = useState<string | null>(null);
  const [withTrashed, setWithTrashed] = useState(false);
  const { items, hasMore, loaded, error, more } = useAssociatable(field, { open, term: term ?? '', withTrashed, type, record, endpoint });
  const input = useRef<HTMLInputElement>(null);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;
  const typed = (term ?? '').trim();
  // A creatable field offers the typed text as a new record, after the matches.
  const creates = onCreate !== undefined && typed !== '';
  const options: PickerOption[] = creates ? [...items, { create: typed }] : items;
  const chosen = isBelongsToValue(value) ? { value: value.value, display: value.display } : null;

  // Swapped in for a select: the focus stays on the field, not on the page.
  useEffect(() => {
    if (takeFocus) {
      input.current?.focus();
    }
  }, [takeFocus]);

  return (
    <Combobox<PickerOption>
      open={open}
      value={chosen}
      // Held here, so a page of results arriving never puts the chosen record back over the typed text.
      inputValue={term ?? chosen?.display ?? ''}
      disabled={disabled}
      items={options}
      // The server already matched the term, on any column it searches, such as an email.
      filter={null}
      itemToStringLabel={(option: PickerOption) => (isCreate(option) ? option.create : option.display)}
      isItemEqualToValue={(option: PickerOption, current: PickerOption) => !isCreate(option) && !isCreate(current) && String(option.value) === String(current.value)}
      onOpenChange={(next) => {
        setOpen(next);

        // The input shows the chosen record again once shut, so the next search starts empty.
        if (!next) {
          setTerm(null);
        }
      }}
      onInputValueChange={(next, details) => {
        if (details.reason === 'input-change') {
          setTerm(next);
        }
      }}
      onValueChange={(option: PickerOption | null) => {
        // Base UI clears the value when the text is emptied, as before typing a new search; the field keeps its record until a pick.
        if (option === null) {
          return;
        }

        if (isCreate(option)) {
          // The sheet takes the text; the field keeps its record until the sheet saves one.
          onCreate?.(option.create);
        } else {
          onChange(picked(option));
        }
      }}
    >
      <ComboboxInput
        ref={input}
        id={id}
        placeholder={field.placeholder ?? __('Select :title', { title: label.replace(/\b[A-Z](?=[a-z])/g, (letter) => letter.toLowerCase()) })}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        // The server refuses a longer term; 100 is limits.search_term_chars by default.
        maxLength={typeof field.searchTermChars === 'number' ? field.searchTermChars : 100}
        onKeyDown={(event) => {
          // A key inside an IME composition is the IME's: its arrows move through candidates, its Enter confirms the text.
          if (composing(event)) {
            event.preventBaseUIHandler();

            return;
          }

          // Enter picks the highlighted option and never submits the form.
          if (event.key === 'Enter') {
            event.preventDefault();
          }
        }}
        // The kit's chevron is a second, unnamed tab stop; a press on the input opens the list as well.
        showTrigger={false}
        className="w-full"
      />
      <ComboboxContent>
        {field.withTrashed === true && <WithTrashedBox checked={withTrashed} onChange={setWithTrashed} />}
        <ComboboxList aria-label={label}>
          {(option: PickerOption) =>
            isCreate(option) ? (
              <ComboboxItem key="create" value={option}>
                {__('Create :resource ":text"', { resource: label.toLowerCase(), text: option.create })}
              </ComboboxItem>
            ) : (
              <ComboboxItem key={option.value} value={option}>
                <span>
                  {option.display}
                  {option.subtitle !== undefined && <span className="block text-xs text-muted-foreground">{option.subtitle}</span>}
                </span>
              </ComboboxItem>
            )
          }
        </ComboboxList>
        {error !== null && (
          <p role="alert" className="px-2 py-1 text-sm text-destructive">
            {error}
          </p>
        )}
        {loaded && items.length === 0 && !creates && <p className="px-2 py-1 text-sm text-muted-foreground">{__('No results')}</p>}
        {hasMore && (
          <Button variant="outline" size="sm" className="mt-1 w-full" onClick={more}>
            {__('More')}
          </Button>
        )}
      </ComboboxContent>
    </Combobox>
  );
}

/** A native select whose options load on first focus; a first page with more turns it into the picker. */
function RelationSelect({ field, value, onChange, id, describedBy, invalid, disabled, type, record, onTooMany }: FormFieldProps & LookupProps & { onTooMany: (pressed: boolean) => void }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  // A press or an opening key, as opposed to the focus alone, which also loads the options.
  const pressed = useRef(false);
  const [withTrashed, setWithTrashed] = useState(false);
  const { items, hasMore } = useAssociatable(field, { open, term: '', withTrashed, type, record });
  const current = isBelongsToValue(value) ? String(value.value) : '';
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;

  useEffect(() => {
    if (hasMore) {
      onTooMany(pressed.current);
    }
  }, [hasMore, onTooMany]);

  return (
    // The press that opens the list bubbles here, so the options load as it opens.
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions -- not a control, it only hears the Select's own press
    <div
      onMouseDown={() => {
        pressed.current = true;
        setOpen(true);
      }}
      onKeyDown={(event) => {
        if (['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) {
          pressed.current = true;
          setOpen(true);
        }
      }}
    >
      <Select
        id={id}
        value={current}
        options={[
          { value: '', label: field.placeholder ?? __('Select :title', { title: label.replace(/\b[A-Z](?=[a-z])/g, (letter) => letter.toLowerCase()) }) },
          ...(isBelongsToValue(value) && !items.some((item) => String(item.value) === current) ? [{ value: current, label: value.display }] : []),
          ...items.map((item) => ({ value: String(item.value), label: item.display })),
        ]}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onFocus={() => {
          setOpen(true);
        }}
        onChange={(next) => {
          const item = items.find((option) => String(option.value) === next);

          if (next === '') {
            onChange(null);
          } else if (item !== undefined) {
            onChange(picked(item));
          }
        }}
        className="w-full"
      />
      {field.withTrashed === true && <WithTrashedBox checked={withTrashed} onChange={setWithTrashed} />}
    </div>
  );
}

export function BelongsToForm(props: FormFieldProps & LookupProps) {
  const { __ } = useTranslation();
  const { field, value, onChange, disabled } = props;
  const [searchable, setSearchable] = useState(field.searchable === true);
  // How the list select gave way to the search picker: on a press it opens at once; on the focus alone it keeps the focus.
  const [swapped, setSwapped] = useState<'open' | 'focus' | null>(null);
  // The text the create sheet starts its title field with, or null while it is shut.
  const [creating, setCreating] = useState<string | null>(null);
  const label = typeof field.singularLabel === 'string' ? field.singularLabel : field.label;

  // A field locked by the page, such as under a via, shows its record and no picker.
  if (field.readonly === true) {
    return <span id={props.id}>{isBelongsToValue(value) ? value.display : '—'}</span>;
  }

  const control = searchable ? (
    <RelationPicker {...props} startOpen={swapped === 'open'} takeFocus={swapped !== null} onCreate={field.creatable === true ? setCreating : undefined} />
  ) : (
    <RelationSelect
      {...props}
      onTooMany={(pressed) => {
        setSearchable(true);
        setSwapped(pressed ? 'open' : 'focus');
      }}
    />
  );

  if (field.creatable !== true) {
    return control;
  }

  return (
    <div className="flex items-center gap-3">
      <div className="min-w-0 flex-1">{control}</div>
      <button
        type="button"
        disabled={disabled}
        className="inline-flex items-center gap-1 rounded-sm text-[13px] font-medium whitespace-nowrap text-link outline-none hover:text-link-hover focus-visible:ring-2 focus-visible:ring-focus disabled:opacity-50"
        onClick={() => {
          setCreating('');
        }}
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {/* Sentence case, as the create form's title: a capitalised word lowers its first letter, an acronym keeps its case. */}
        {__('New :resource', { resource: label.replace(/\b[A-Z](?=[a-z])/g, (letter) => letter.toLowerCase()) })}
      </button>
      {creating !== null && (
        <Suspense fallback={null}>
          <CreateRelationSheet
            field={field}
            record={props.record}
            text={creating}
            onCreated={onChange}
            onClose={() => {
              setCreating(null);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
