import { useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { Suspense, useState, type ComponentType } from 'react';
import { lazyNamed } from '../../lib/lazy';
import { stringValue, type FormFieldProps } from '../registry';
import { filterOptions, groupRuns, inputClass, labelOf, optionsOf, stringsOf, type Option } from './options';

/** The options a remote field found on the server, and the search that asks for them. */
export interface RemoteSource {
  options: Option[];
  query: string;
  onQuery: (query: string) => void;
  onFocus: () => void;
}

// The server search loads with the first remote select a form draws, not with the index.
export const RemoteChoice = lazyNamed(() => import('./RemoteChoice').then((module) => module.RemoteChoice));

/** A remote field's form, holding an input's height while the search chunk loads. */
export function DeferredRemote({ Form, ...props }: FormFieldProps & { Form: ComponentType<FormFieldProps & { remote?: RemoteSource }> }) {
  return (
    <Suspense fallback={<div className="h-9" aria-hidden="true" />}>
      <RemoteChoice Form={Form} {...props} />
    </Suspense>
  );
}

/** The filter box above a searchable list. */
export function SearchBox({ query, onQuery, disabled, onFocus }: { query: string; onQuery: (query: string) => void; disabled: boolean; onFocus?: () => void }) {
  const { __ } = useTranslation();

  return (
    <input
      type="search"
      aria-label={__('Search')}
      placeholder={__('Search')}
      value={query}
      disabled={disabled}
      onFocus={onFocus}
      onChange={(event) => {
        onQuery(event.target.value);
      }}
      className={`${inputClass} mb-1`}
    />
  );
}

/** Options as <option> elements, each group in its own <optgroup>. */
export function OptionElements({ options }: { options: Option[] }) {
  return groupRuns(options).map((run) =>
    run.group === undefined ? (
      run.options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))
    ) : (
      <optgroup key={`group:${run.group}`} label={run.group}>
        {run.options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </optgroup>
    ),
  );
}

export function SelectForm(props: FormFieldProps & { remote?: RemoteSource }) {
  const { field, value, onChange, id, describedBy, invalid, disabled, remote } = props;
  const [query, setQuery] = useState('');

  if (field.remote === true && remote === undefined) {
    return <DeferredRemote Form={SelectForm} {...props} />;
  }

  const current = stringValue(value);
  const visible = remote?.options ?? filterOptions(optionsOf(field), query, [current]);

  return (
    <div>
      {remote !== undefined ? (
        <SearchBox query={remote.query} onQuery={remote.onQuery} disabled={disabled} onFocus={remote.onFocus} />
      ) : (
        field.searchable === true && <SearchBox query={query} onQuery={setQuery} disabled={disabled} />
      )}
      <select
        id={id}
        name={field.attribute}
        value={current}
        onFocus={remote?.onFocus}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(event) => {
          onChange(event.target.value === '' ? null : event.target.value);
        }}
        className={inputClass}
      >
        <option value="">{field.placeholder ?? ''}</option>
        <OptionElements options={visible} />
      </select>
    </div>
  );
}

export function MultiSelectForm(props: FormFieldProps & { remote?: RemoteSource }) {
  const { field, value, onChange, id, describedBy, invalid, disabled, remote } = props;
  const { __ } = useTranslation();
  const [query, setQuery] = useState('');

  if (field.remote === true && remote === undefined) {
    return <DeferredRemote Form={MultiSelectForm} {...props} />;
  }

  const options = remote?.options ?? optionsOf(field);
  const selected = stringsOf(value);
  // A selected option stays in the list while the search hides its neighbours, or the change event would drop it.
  const visible = remote === undefined ? filterOptions(options, query, selected) : options;

  return (
    <div className="space-y-2">
      {remote !== undefined ? (
        <SearchBox query={remote.query} onQuery={remote.onQuery} disabled={disabled} onFocus={remote.onFocus} />
      ) : (
        field.searchable === true && <SearchBox query={query} onQuery={setQuery} disabled={disabled} />
      )}
      <select
        id={id}
        name={field.attribute}
        multiple
        value={selected}
        onFocus={remote?.onFocus}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(event) => {
          onChange(Array.from(event.target.selectedOptions, (option) => option.value));
        }}
        className={`${inputClass} h-auto min-h-24`}
      >
        <OptionElements options={visible} />
      </select>
      {field.withSelectedTags !== false && selected.length > 0 && (
        <ul className="flex flex-wrap gap-1">
          {selected.map((item) => {
            const label = labelOf(options, item);

            return (
              <li key={item} className="inline-flex items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-xs">
                {label}
                <button
                  type="button"
                  aria-label={__('Remove :item', { item: label })}
                  disabled={disabled}
                  onClick={() => {
                    onChange(selected.filter((other) => other !== item));
                  }}
                >
                  <X aria-hidden className="size-3" />
                </button>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
