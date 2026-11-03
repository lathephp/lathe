import { useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { Suspense, useState, type ComponentType } from 'react';
import { Select } from '../../components/ui/Select';
import { lazyNamed } from '../../lib/lazy';
import { stringValue, type FormFieldProps } from '../registry';
import { filterOptions, inputClass, labelOf, optionsOf, stringsOf, type Option } from './options';

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

/**
 * The filter box above a searchable list. Enter never submits the form around it: it runs
 * onEnter, if given. Escape clears a search and stops there; with no search it passes on.
 */
export function SearchBox({
  query,
  onQuery,
  disabled,
  onFocus,
  onEnter,
}: {
  query: string;
  onQuery: (query: string) => void;
  disabled: boolean;
  onFocus?: () => void;
  onEnter?: () => void;
}) {
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
      onKeyDown={(event) => {
        if (event.key === 'Enter') {
          event.preventDefault();

          if (!event.nativeEvent.isComposing) {
            onEnter?.();
          }
        } else if (event.key === 'Escape' && query !== '') {
          event.preventDefault();
          event.stopPropagation();
          onQuery('');
        }
      }}
      className={`${inputClass} mb-1`}
    />
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
  // Enter picks the first option the search matches, not the current one the list keeps. A remote
  // list answers after its debounce, so Enter there picks nothing.
  const pickFirst = () => {
    const first = query.trim() === '' ? undefined : filterOptions(optionsOf(field), query, [])[0];

    if (first !== undefined) {
      onChange(first.value);
    }
  };

  return (
    <div>
      {remote !== undefined ? (
        <SearchBox query={remote.query} onQuery={remote.onQuery} disabled={disabled} onFocus={remote.onFocus} />
      ) : (
        field.searchable === true && <SearchBox query={query} onQuery={setQuery} disabled={disabled} onEnter={pickFirst} />
      )}
      <Select
        id={id}
        value={current}
        options={[{ value: '', label: field.placeholder ?? '—' }, ...visible]}
        onFocus={remote?.onFocus}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(next) => {
          onChange(next === '' ? null : next);
        }}
        className="w-full"
      />
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
      <Select
        multiple
        id={id}
        value={selected}
        options={visible}
        onFocus={remote?.onFocus}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={onChange}
        className="w-full"
      />
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
