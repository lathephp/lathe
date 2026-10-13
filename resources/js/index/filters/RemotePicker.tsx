import { queryKeys, request, useQuery, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useEffect, useRef, useState } from 'react';
import type { FilterControlProps, FilterOption } from '../types';
import { FilterFieldset, fieldClass } from './FilterFieldset';

// Long enough to wait out a word being typed.
const debounceMs = 300;

interface OptionsPage {
  options: FilterOption[];
  hasMore: boolean;
}

interface OptionPageProps extends Pick<FilterControlProps, 'filter' | 'endpoint' | 'resource'> {
  term: string;
  page: number;
  last: boolean;
  selected: string[];
  onChoose: (value: string) => void;
  onMore: () => void;
}

/** One server page of options. Each loaded page keeps its own cached query. */
function OptionPage({ filter, endpoint, resource, term, page, last, selected, onChoose, onMore }: OptionPageProps) {
  const { __ } = useTranslation();
  const { data } = useQuery({
    queryKey: queryKeys.filterOptions(resource, filter.key, term, page),
    queryFn: ({ signal }) =>
      request<OptionsPage>(
        `${endpoint}/${encodeURIComponent(filter.key)}/options?${new URLSearchParams({ q: term, page: String(page) }).toString()}`,
        { signal },
      ),
  });

  return (
    <>
      {data?.options.map((option) => (
        <Button
          key={option.value}
          variant={selected.includes(option.value) ? 'default' : 'ghost'}
          size="sm"
          className="justify-start"
          aria-pressed={selected.includes(option.value)}
          onClick={() => {
            onChoose(option.value);
          }}
        >
          {option.label}
        </Button>
      ))}
      {last && data?.hasMore === true && (
        <Button variant="outline" size="sm" onClick={onMore}>
          {__('More')}
        </Button>
      )}
    </>
  );
}

/** Options too many to send with the page: searched and paged from the server, 25 at a time. */
export function RemotePicker({ filter, value, onChange, endpoint, resource }: FilterControlProps) {
  const { __ } = useTranslation();
  const [input, setInput] = useState('');
  const [term, setTerm] = useState('');
  const [pages, setPages] = useState(1);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const multiple = filter.component === 'multi-select-filter';
  const selected = Array.isArray(value) ? value : typeof value === 'string' && value !== '' ? [value] : [];

  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  return (
    <FilterFieldset legend={filter.name}>
      <input
        type="search"
        className={fieldClass}
        value={input}
        placeholder={__('Search')}
        aria-label={__('Search :filter', { filter: filter.name })}
        onChange={(event) => {
          const next = event.target.value;
          setInput(next);
          clearTimeout(timer.current);
          timer.current = setTimeout(() => {
            setTerm(next.trim());
            setPages(1);
          }, debounceMs);
        }}
      />
      <div className="grid max-h-60 gap-1 overflow-y-auto">
        {Array.from({ length: pages }, (_, index) => (
          <OptionPage
            key={index}
            filter={filter}
            endpoint={endpoint}
            resource={resource}
            term={term}
            page={index + 1}
            last={index + 1 === pages}
            selected={selected}
            onChoose={(choice) => {
              // A second click on the chosen option clears it.
              if (multiple) {
                onChange(selected.includes(choice) ? selected.filter((item) => item !== choice) : [...selected, choice]);
              } else {
                onChange(selected.includes(choice) ? '' : choice);
              }
            }}
            onMore={() => {
              setPages(pages + 1);
            }}
          />
        ))}
      </div>
    </FilterFieldset>
  );
}
