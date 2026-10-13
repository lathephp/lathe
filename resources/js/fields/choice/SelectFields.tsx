import { useTranslation } from '@lathephp/core';
import { useState } from 'react';
import { stringValue, type DetailFieldProps, type FormFieldProps, type IndexFieldProps } from '../registry';
import { filterOptions, groupRuns, inputClass, labelOf, optionsOf, stringsOf, type Option } from './options';

/** The filter box above a searchable list. */
export function SearchBox({ query, onQuery, disabled }: { query: string; onQuery: (query: string) => void; disabled: boolean }) {
  const { __ } = useTranslation();

  return (
    <input
      type="search"
      aria-label={__('Search')}
      placeholder={__('Search')}
      value={query}
      disabled={disabled}
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

export function SelectForm({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const [query, setQuery] = useState('');
  const current = stringValue(value);
  const visible = filterOptions(optionsOf(field), query, [current]);

  return (
    <div>
      {field.searchable === true && <SearchBox query={query} onQuery={setQuery} disabled={disabled} />}
      <select
        id={id}
        name={field.attribute}
        value={current}
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

function shown(field: IndexFieldProps['field'], value: unknown): string {
  return field.displayUsingLabels === true ? labelOf(optionsOf(field), stringValue(value)) : stringValue(value);
}

export function SelectIndex({ field, cell }: IndexFieldProps) {
  return shown(field, cell.value);
}

export function SelectDetail({ field }: DetailFieldProps) {
  const text = shown(field, field.value);

  return text === '' ? <span className="text-muted-foreground">—</span> : text;
}

/** Tags for a list of values, each with the label of its option. */
export function Tags({ field, value }: { field: IndexFieldProps['field']; value: unknown }) {
  const options = optionsOf(field);

  return (
    <span className="inline-flex flex-wrap gap-1">
      {stringsOf(value).map((item) => (
        <span key={item} className="rounded-md bg-muted px-2 py-0.5 text-xs">
          {labelOf(options, item)}
        </span>
      ))}
    </span>
  );
}
