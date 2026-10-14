import { useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { DetailFieldProps, FormFieldProps, IndexFieldProps } from '../registry';
import { filterOptions, inputClass, labelOf, optionsOf, stringsOf } from './options';
import { DeferredRemote, OptionElements, SearchBox, Tags, type RemoteSource } from './SelectFields';

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

export function MultiSelectIndex({ field, cell }: IndexFieldProps) {
  return <Tags field={field} value={cell.value} />;
}

export function MultiSelectDetail({ field }: DetailFieldProps) {
  return stringsOf(field.value).length === 0 ? <span className="text-muted-foreground">—</span> : <Tags field={field} value={field.value} />;
}
