import { queryKeys, request, useQuery, useTranslation } from '@lathephp/core';
import { Combobox, ComboboxInput, ComboboxItem, ComboboxList } from '@lathephp/ui/combobox';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../../components/ui/Button';
import type { FilterControlProps, FilterOption } from '../types';
import { FilterFieldset } from './FilterFieldset';

// Long enough to wait out a word being typed.
const debounceMs = 300;

interface OptionsPage {
  /** A relation's options carry their keys as numbers. */
  options: (Omit<FilterOption, 'value'> & { value: string | number })[];
  hasMore: boolean;
}

type Page = Pick<FilterControlProps, 'filter' | 'endpoint' | 'resource'> & { term: string; page: number };

/** One server page of options. Each loaded page keeps its own cached query. */
function useOptionPage({ filter, endpoint, resource, term, page }: Page) {
  return useQuery({
    queryKey: queryKeys.filterOptions(resource, filter.key, term, page),
    queryFn: ({ signal }) =>
      request<OptionsPage>(
        `${endpoint}/${encodeURIComponent(filter.key)}/options?${new URLSearchParams({ q: term, page: String(page) }).toString()}`,
        { signal },
      ),
  });
}

/** The options of one page. */
function OptionPage(props: Page) {
  const { data } = useOptionPage(props);

  return (
    <>
      {data?.options.map((option) => (
        // The URL and the selection hold text.
        <ComboboxItem key={String(option.value)} value={{ label: option.label, value: String(option.value) }}>
          {option.label}
        </ComboboxItem>
      ))}
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
  const { data: last } = useOptionPage({ filter, endpoint, resource, term, page: pages });

  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  return (
    <FilterFieldset legend={filter.name}>
      {/* The server matches the term, so the combobox keeps every option it is given. */}
      <Combobox
        multiple
        inline
        open
        filter={null}
        value={selected.map((item) => ({ label: item, value: item }))}
        inputValue={input}
        itemToStringLabel={(item: { label: string }) => item.label}
        isItemEqualToValue={(item: { label: string; value: string }, chosen: { label: string; value: string }) => item.value === chosen.value}
        onValueChange={(all: { label: string; value: string }[]) => {
          const values = all.map((item) => item.value);

          if (multiple) {
            onChange(values);
          } else {
            // A second pick replaces the first; picking the chosen option again clears it.
            onChange(values.find((item) => !selected.includes(item)) ?? '');
          }
        }}
        onInputValueChange={(next, details) => {
          if (details.reason !== 'input-change') {
            return;
          }

          setInput(next);
          clearTimeout(timer.current);
          timer.current = setTimeout(() => {
            setTerm(next.trim());
            setPages(1);
          }, debounceMs);
        }}
      >
        <ComboboxInput showTrigger={false} placeholder={__('Search')} aria-label={__('Search :filter', { filter: filter.name })} />
        <ComboboxList className="grid max-h-60 gap-1">
          {Array.from({ length: pages }, (_, index) => (
            <OptionPage key={index} filter={filter} endpoint={endpoint} resource={resource} term={term} page={index + 1} />
          ))}
        </ComboboxList>
      </Combobox>
      {last?.hasMore === true && (
        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            setPages(pages + 1);
          }}
        >
          {__('More')}
        </Button>
      )}
    </FilterFieldset>
  );
}
