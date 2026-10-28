import { usePage } from '@inertiajs/react';
import { request, useTranslation } from '@lathephp/core';
import { Combobox, ComboboxChip, ComboboxChips, ComboboxChipsInput } from '@lathephp/ui/combobox';
import { Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { SharedProps } from '../types';
import { chipName } from './FilterBar';
import { deferFilterVisit } from './FilterChips';
import { chipFromResults, type FilterChip, type LookupResult, parseToken, splitInput, toUrlState } from './filterTokens';
import type { IndexFilter, IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';

// A chip change requests the list this long after the last one, so chips added together send one visit.
const waitMs = 300;

interface FilterBarEditorProps {
  label: string;
  url: string;
  /** The chips the URL holds, with the first paint's labels. */
  urlChips: FilterChip[];
  text: string;
  /** Whether the first paint's input had the focus when the editor took over. */
  focus: boolean;
  onType: (text: string) => void;
  onSettle: (text: string) => void;
  visit: (changes: IndexUrlState) => void;
}

const applied = (chip: FilterChip) => chip.error === undefined;

/** The filter bar once focused: a Base UI chips input where Enter turns key:value tokens into chips and words into q. */
export function FilterBarEditor({ label, url, urlChips, text, focus, onType, onSettle, visit }: FilterBarEditorProps) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource, filters, basePath } = page.props;
  const [chips, setChips] = useState(urlChips);
  const [seen, setSeen] = useState(url);
  const input = useRef<HTMLInputElement>(null);
  // The chips as last drawn, for an Enter that waited on a lookup.
  const latest = useRef(chips);

  // A visit landed: the URL's chips come back, and the red ones, which the URL never holds, stay.
  if (url !== seen) {
    setSeen(url);
    setChips([...urlChips, ...chips.filter((chip) => !applied(chip))]);
  }

  useEffect(() => {
    latest.current = chips;
  });

  // Takes the focus where the first paint's input had it, the caret after the text typed meanwhile.
  // In the commit itself, so no frame shows the bar without its focus.
  useLayoutEffect(() => {
    const element = input.current;

    if (focus && element !== null) {
      element.focus();
      element.setSelectionRange(element.value.length, element.value.length);
    }
  }, [focus]);

  function send(next: FilterChip[], changes: IndexUrlState = {}) {
    const state = toUrlState(next);

    deferFilterVisit(() => {
      visit({ filter: state.filter, trashed: state.trashed, ...changes });
    }, waitMs);
  }

  async function lookup(filter: IndexFilter, term: string): Promise<LookupResult[]> {
    const query = new URLSearchParams({ q: term, page: '1' }).toString();

    try {
      const found = await request<{ options: LookupResult[] }>(`${basePath}/api/resources/${resource.uriKey}/filters/${encodeURIComponent(filter.key)}/options?${query}`);

      return found?.options ?? [];
    } catch {
      // A failed search finds no match, which the chip shows in red.
      return [];
    }
  }

  async function submit() {
    const typed = text;
    const { tokens, words } = splitInput(typed);
    const added = await Promise.all(
      tokens.map(async (token) => {
        const parsed = parseToken(token, filters?.available ?? [], { softDeletes: resource.softDeletes }, __);

        return 'chip' in parsed ? parsed.chip : chipFromResults(parsed.lookup.filter, parsed.lookup.term, await lookup(parsed.lookup.filter, parsed.lookup.term), __);
      }),
    );
    // A new chip replaces the one its filter had.
    const replaced = new Set(added.filter(applied).map((chip) => chip.key));
    const next = [...latest.current.filter((chip) => !applied(chip) || !replaced.has(chip.key)), ...added];
    // The words left after Enter are the whole search, so a search a half-typed key started goes; a Resource that cannot search ignores them.
    const searched = new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? '';
    const changes: IndexUrlState = resource.searchable && words !== searched ? { q: words } : {};

    setChips(next);
    onSettle(resource.searchable ? words : '');

    if (added.some(applied) || 'q' in changes) {
      send(next, changes);
    }
  }

  return (
    <Combobox
      multiple
      open={false}
      value={chips}
      inputValue={text}
      itemToStringLabel={(chip: FilterChip) => chip.label}
      onValueChange={(next: FilterChip[]) => {
        setChips(next);

        // Removing a red chip changes no rows.
        if (chips.some((chip) => applied(chip) && !next.includes(chip))) {
          send(next);
        }
      }}
      onInputValueChange={(next, details) => {
        // Only the person's typing changes the text; it stays until Enter.
        if (details.reason === 'input-change') {
          onType(next);
        }
      }}
    >
      <ComboboxChips className="lathe-bar-box">
        <Search aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
        {chips.map((chip, index) => (
          <ComboboxChip
            key={`${String(index)}:${chip.token}`}
            data-error={applied(chip) ? undefined : ''}
            title={applied(chip) ? undefined : chip.token}
            removeLabel={__('Remove :filter filter', { filter: chipName(chip, page.props, __) })}
          >
            {chip.error ?? chip.label}
          </ComboboxChip>
        ))}
        <ComboboxChipsInput
          ref={input}
          placeholder={label}
          aria-label={label}
          aria-keyshortcuts="/"
          onKeyDown={(event) => {
            if (event.key === 'Enter') {
              event.preventDefault();
              void submit();
            }
          }}
        />
        <kbd aria-hidden="true">/</kbd>
      </ComboboxChips>
    </Combobox>
  );
}
