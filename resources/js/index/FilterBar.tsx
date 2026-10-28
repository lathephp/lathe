import { usePage } from '@inertiajs/react';
import { addShortcut, type Translator, useTranslation } from '@lathephp/core';
import { Search, X } from 'lucide-react';
import { Suspense, useEffect, useRef, useState } from 'react';
import type { SharedProps } from '../types';
import type { FilterChip } from './filterTokens';
import { activeFilters, cancelFilterVisit, chipValue } from './FilterChips';
import { FilterBarEditor } from './lazy';
import type { IndexProps, SavedTab } from './types';
import { type FilterValue, type IndexUrlState, parseQuery } from './useIndexState';

type BarProps = Pick<IndexProps, 'resource' | 'filters' | 'staleFilters' | 'tabs'>;

/** The saved view ?saved_tab= opens, if any. */
export function openView({ tabs }: BarProps): SavedTab | undefined {
  return tabs?.saved?.find((tab) => tab.id === tabs.activeSaved);
}

/** A value as the server cleans it (numbers, booleans, an empty side as null) in the URL's shape. */
function urlShape(value: unknown): FilterValue {
  const text = (item: unknown) => (typeof item === 'boolean' ? (item ? '1' : '0') : String(item));

  if (Array.isArray(value)) {
    return value.map(text);
  }

  if (typeof value === 'object' && value !== null) {
    return Object.fromEntries(Object.entries(value).filter(([, side]) => side !== null && side !== '').map(([side, item]) => [side, text(item)]));
  }

  return text(value);
}

/** The chips' filters and trashed choice as the URL writes them. */
export function chipsState(chips: FilterChip[]): Pick<IndexUrlState, 'filter' | 'trashed'> {
  return {
    filter: Object.fromEntries(chips.filter((chip) => chip.key !== 'trashed').map((chip) => [chip.key, chip.value ?? ''])),
    trashed: chips.find((chip) => chip.key === 'trashed')?.value as string | undefined,
  };
}

/** The chips the URL holds, over those of the saved view it opens: one per filter key the Resource still declares, then trashed. */
export function urlChips(url: string, props: BarProps, __: Translator): FilterChip[] {
  const { resource, filters, staleFilters = [] } = props;
  const view = Object.fromEntries(Object.entries(openView(props)?.filters ?? {}).map(([key, value]) => [key, urlShape(value)]));
  const chips: FilterChip[] = Object.entries({ ...view, ...activeFilters(url, staleFilters) }).map(([key, value]) => {
    const filter = filters?.available.find((candidate) => candidate.key === key);
    const label = `${filter?.name ?? key}: ${chipValue(value, filter, filters?.labels?.[key])}`;

    return { token: label, key, value, label };
  });
  const { trashed } = parseQuery(url.split('?')[1] ?? '');

  return resource.softDeletes && trashed !== undefined
    ? [...chips, { token: `trashed:${trashed}`, key: 'trashed', value: trashed, label: `${__('Trashed')}: ${trashed}` }]
    : chips;
}

/** The filter's name, which the chip's remove button says. */
export function chipName(chip: FilterChip, { filters }: BarProps, __: Translator): string {
  return chip.key === 'trashed' ? __('Trashed') : (filters?.available.find((filter) => filter.key === chip.key)?.name ?? chip.key);
}

interface FilterBarProps {
  q: string | undefined;
  visit: (changes: IndexUrlState) => void;
}

/**
 * The filter bar's first paint: the URL's chips and a plain input holding q. The editor, with
 * Base UI and the token parser, loads on the first focus and takes over in the same box.
 */
export function FilterBar({ q = '', visit }: FilterBarProps) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource } = page.props;
  const view = openView(page.props);
  // A chip or search changed on a saved view leaves it, keeping the view's search and sort.
  const leave: IndexUrlState =
    view === undefined ? {} : { saved_tab: undefined, q: q === '' ? (view.q ?? undefined) : q, sort: parseQuery(page.url.split('?')[1] ?? '').sort ?? view.sort ?? undefined };
  const [text, setText] = useState(q === '' ? (view?.q ?? '') : q);
  const [seen, setSeen] = useState(q);
  // Null until the editor has loaded after a focus; then whether the input had the focus at that moment,
  // and whether a press on it gave that focus.
  const [editor, setEditor] = useState<{ focus: boolean; pressed: boolean } | null>(null);
  const box = useRef<HTMLDivElement>(null);
  // A press focuses the input before the editor loads, so the click that opens the list lands here.
  const pressed = useRef(false);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const chips = urlChips(page.url, page.props, __);
  const named = { resource: resource.label.toLowerCase() };
  const label = resource.searchable ? __('Search :resource', named) : __('Filter :resource', named);

  // The page keeps its state across visits, so a q changed elsewhere (Clear filters) is copied in.
  // The bar's own search lands as its trimmed text and leaves the text alone.
  if (q !== seen) {
    setSeen(q);

    if (q !== text.trim()) {
      setText(q);
    }
  }

  useEffect(
    () =>
      addShortcut('/', (event) => {
        // Keeps the slash out of the input it focuses.
        event.preventDefault();
        box.current?.querySelector('input')?.focus();
      }),
    [],
  );

  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );
  /** Typing: plain words search after the Resource's debounce; text with a key:value waits for Enter. */
  function type(next: string) {
    setText(next);
    clearTimeout(timer.current);

    if (resource.searchable && !next.includes(':')) {
      timer.current = setTimeout(() => {
        // An empty term leaves q out of the URL.
        visit(view === undefined ? { q: next.trim() } : { ...leave, ...chipsState(chips), q: next.trim() });
      }, resource.definition.debounce * 1000);
    }
  }

  /** The text Enter leaves behind; its search goes with the chips, so the live one waiting is dropped. */
  function settle(next: string) {
    clearTimeout(timer.current);
    setText(next);
  }

  function edit() {
    void FilterBarEditor.preload().then(() => {
      setEditor({ focus: box.current?.contains(document.activeElement) === true, pressed: pressed.current });
    });
  }

  function remove(chip: FilterChip) {
    cancelFilterVisit();
    visit({ ...leave, ...chipsState(chips.filter((other) => other !== chip)) });
  }

  return (
    <div
      ref={box}
      data-lathe-filter-bar=""
      className="lathe-bar"
      // Pointing at the bar starts loading the editor, so a click finds it ready.
      onPointerEnter={() => {
        void FilterBarEditor.preload();
      }}
    >
      {editor === null ? (
        <div data-slot="combobox-chips" className="cn-combobox-chips lathe-bar-box">
          <Search aria-hidden="true" className="size-3.5 shrink-0 text-muted-foreground" />
          {chips.map((chip) => (
            <div key={chip.key} data-slot="combobox-chip" className="cn-combobox-chip">
              <span>{chip.label}</span>
              <button
                type="button"
                data-slot="combobox-chip-remove"
                className="cn-combobox-chip-remove"
                aria-label={__('Remove :filter filter', { filter: chipName(chip, page.props, __) })}
                onClick={() => {
                  remove(chip);
                }}
              >
                <X aria-hidden="true" className="size-3" />
              </button>
            </div>
          ))}
          <input
            type="search"
            data-slot="combobox-chip-input"
            className="cn-combobox-chip-input min-w-16 flex-1 outline-none"
            value={text}
            placeholder={label}
            aria-label={label}
            aria-keyshortcuts="/"
            onChange={(event) => {
              type(event.target.value);
            }}
            onPointerDown={() => {
              pressed.current = true;
            }}
            onFocus={edit}
          />
          <kbd aria-hidden="true">/</kbd>
        </div>
      ) : (
        <Suspense fallback={null}>
          <FilterBarEditor label={label} url={page.url} urlChips={chips} leave={leave} text={text} focus={editor.focus} pressed={editor.pressed} onType={type} onSettle={settle} visit={visit} />
        </Suspense>
      )}
    </div>
  );
}
