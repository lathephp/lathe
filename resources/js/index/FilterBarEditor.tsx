import { usePage } from '@inertiajs/react';
import { request, useTranslation } from '@lathephp/core';
import {
  Combobox,
  ComboboxChip,
  ComboboxChips,
  ComboboxChipsInput,
  ComboboxContent,
  ComboboxItem,
  ComboboxList,
  useComboboxAnchor,
} from '@lathephp/ui/combobox';
import { Search } from 'lucide-react';
import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { SharedProps } from '../types';
import { chipName } from './FilterBar';
import { deferFilterVisit } from './FilterChips';
import { chipFromResults, type FilterChip, type LookupResult, parseToken, splitInput, suggest, type Suggestion, toUrlState } from './filterTokens';
import type { IndexFilter, IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';

// A chip change requests the list this long after the last one, so chips added together send one visit.
const waitMs = 300;

// The list searches a relation's options this long after the last keystroke.
const searchMs = 150;

const isSuggestion = (item: FilterChip | Suggestion): item is Suggestion => 'text' in item || 'chip' in item;

/** A filter's options matching the term, from the endpoint under `base`. */
async function lookup(base: string, filter: IndexFilter, term: string): Promise<LookupResult[]> {
  const query = new URLSearchParams({ q: term, page: '1' }).toString();

  try {
    const found = await request<{ options: LookupResult[] }>(`${base}/filters/${encodeURIComponent(filter.key)}/options?${query}`);

    return found?.options ?? [];
  } catch {
    // A failed search finds no match, which the chip shows in red.
    return [];
  }
}

interface FilterBarEditorProps {
  label: string;
  url: string;
  /** The chips the URL holds, with the first paint's labels. */
  urlChips: FilterChip[];
  text: string;
  /** Whether the first paint's input had the focus when the editor took over. */
  focus: boolean;
  /** Whether a press on the first paint's input loaded the editor, which opens the list as a click on it does. */
  pressed: boolean;
  onType: (text: string) => void;
  onSettle: (text: string) => void;
  visit: (changes: IndexUrlState) => void;
}

const applied = (chip: FilterChip) => chip.error === undefined;

/** The filter bar once focused: a Base UI chips input where Enter turns key:value tokens into chips and words into q. */
export function FilterBarEditor({ label, url, urlChips, text, focus, pressed, onType, onSettle, visit }: FilterBarEditorProps) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource, filters, basePath } = page.props;
  const [chips, setChips] = useState(urlChips);
  const [seen, setSeen] = useState(url);
  const [open, setOpen] = useState(pressed);
  // The relation options the last search found, kept while the next one loads.
  const [found, setFound] = useState<{ key: string; options: LookupResult[] } | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const anchor = useComboboxAnchor();
  const highlighted = useRef<FilterChip | Suggestion | undefined>(undefined);
  // The chips as last drawn, for an Enter that waited on a lookup.
  const latest = useRef(chips);
  const base = `${basePath}/api/resources/${resource.uriKey}`;
  const suggested = suggest(text, filters?.available ?? [], { softDeletes: resource.softDeletes }, __);
  const searching = 'lookup' in suggested ? suggested.lookup : undefined;
  const items: Suggestion[] =
    'items' in suggested
      ? suggested.items
      : searching !== undefined && found?.key === searching.filter.key
        ? found.options.map((option) => ({ label: option.label, chip: chipFromResults(searching.filter, option.label, [option], __) }))
        : [];
  const help = 'help' in suggested ? suggested.help : undefined;
  const searchFilter = searching?.filter;
  const searchTerm = searching?.term;

  useEffect(() => {
    if (searchFilter === undefined || searchTerm === undefined) {
      return;
    }

    let current = true;
    const timer = setTimeout(() => {
      void lookup(base, searchFilter, searchTerm).then((options) => {
        if (current) {
          setFound({ key: searchFilter.key, options });
        }
      });
    }, searchMs);

    return () => {
      current = false;
      clearTimeout(timer);
    };
  }, [base, searchFilter, searchTerm]);

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

  async function submit() {
    const typed = text;
    const { tokens, words } = splitInput(typed);
    const added = await Promise.all(
      tokens.map(async (token) => {
        const parsed = parseToken(token, filters?.available ?? [], { softDeletes: resource.softDeletes }, __);

        return 'chip' in parsed ? parsed.chip : chipFromResults(parsed.lookup.filter, parsed.lookup.term, await lookup(base, parsed.lookup.filter, parsed.lookup.term), __);
      }),
    );
    // A new chip replaces the one its filter had.
    const replaced = new Set(added.filter(applied).map((chip) => chip.key));
    const next = [...latest.current.filter((chip) => !applied(chip) || !replaced.has(chip.key)), ...added];
    // The words left after Enter are the whole search, so a search a half-typed key started goes; a Resource that cannot search ignores them.
    const searched = new URLSearchParams(url.split('?')[1] ?? '').get('q') ?? '';
    const changes: IndexUrlState = resource.searchable && words !== searched ? { q: words } : {};

    setChips(next);
    setOpen(false);
    onSettle(resource.searchable ? words : '');

    if (added.some(applied) || 'q' in changes) {
      send(next, changes);
    }
  }

  /** A filter puts its token in the input and lists its values; a value adds its chip at once. */
  function pick(item: Suggestion) {
    if ('text' in item) {
      onSettle(item.text);

      return;
    }

    const next = [...chips.filter((chip) => !applied(chip) || chip.key !== item.chip.key), item.chip];

    setChips(next);
    setOpen(false);
    onSettle('');
    send(next);
  }

  return (
    <Combobox
      multiple
      open={open && (items.length > 0 || help !== undefined)}
      value={chips}
      inputValue={text}
      itemToStringLabel={(item: FilterChip | Suggestion) => item.label}
      onOpenChange={(next, details) => {
        // A pick decides itself whether the list stays: a filter keeps it open for its values.
        if (!next && details.reason === 'item-press') {
          details.cancel();

          return;
        }

        setOpen(next);
      }}
      onItemHighlighted={(item: FilterChip | Suggestion | undefined) => {
        highlighted.current = item;
      }}
      onValueChange={(all: (FilterChip | Suggestion)[]) => {
        const picked = all.find(isSuggestion);

        if (picked !== undefined) {
          pick(picked);

          return;
        }

        const next = all.filter((item): item is FilterChip => !isSuggestion(item));

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
      <ComboboxChips ref={anchor} className="lathe-bar-box">
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
            // Enter on a highlighted item is Base UI's pick; otherwise it parses the whole text.
            if (event.key === 'Enter' && !(open && highlighted.current !== undefined)) {
              event.preventDefault();
              event.preventBaseUIHandler();
              void submit();
            }
          }}
        />
        <kbd aria-hidden="true">/</kbd>
      </ComboboxChips>
      <ComboboxContent anchor={anchor} className="lathe-bar-list">
        <ComboboxList>
          {items.map((item, index) => (
            <ComboboxItem key={`${String(index)}:${item.label}`} value={item}>
              {item.label}
              {'text' in item ? (
                <>
                  {' '}
                  <span className="lathe-bar-hint">{item.hint}</span>
                </>
              ) : null}
            </ComboboxItem>
          ))}
        </ComboboxList>
        {help === undefined ? null : <p className="lathe-bar-help">{help}</p>}
      </ComboboxContent>
    </Combobox>
  );
}
