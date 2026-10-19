import { router } from '@inertiajs/react';
import { ApiError, pauseShortcuts, queryKeys, request, resumeShortcuts, useQuery, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ArrowDown, ArrowUp, CornerDownLeft, History, Search, X } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '../lib/cn';
import { initials, type SearchResponse, type SearchResult } from './results';
import { useRecentSearches } from './useRecentSearches';

interface Props {
  basePath: string;
  /** Seconds the input waits after the last keystroke. */
  debounce: number;
  onClose: () => void;
}

// A key hint: 20 px, bordered, 11 px text.
const kbd = 'inline-flex h-5 min-w-5 items-center justify-center rounded border border-border bg-background px-[5px] text-[11px] leading-none font-medium text-muted-foreground';

/** A row's 28 px picture, its initials, or for a recent search the history mark. A phone shows none. */
function Avatar({ result, recent }: { result: SearchResult; recent: boolean }) {
  const shape = result.rounded ? 'rounded-full' : 'rounded-lg';
  const box = 'size-7 shrink-0 max-md:hidden';

  if (recent || result.avatar === null) {
    return (
      <span aria-hidden="true" className={cn(box, 'flex items-center justify-center bg-muted text-[11px] font-medium text-muted-foreground', recent ? 'rounded-lg' : shape)}>
        {recent ? <History className="size-3.5" /> : initials(result.title)}
      </span>
    );
  }

  return <img src={result.avatar} alt="" className={cn(box, 'object-cover', shape)} />;
}

/**
 * The search palette. It mounts when it opens and unmounts when it closes, so mounting
 * pauses the shortcuts, takes the focus and (on unmount) gives it back to the opener.
 */
/** The text with each word of the term marked where it occurs, ignoring case. */
function Marked({ text, term }: { text: string; term: string }) {
  const words = term.split(/\s+/).filter((word) => word !== '').map((word) => word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

  if (words.length === 0) {
    return text;
  }

  return text.split(new RegExp(`(${words.join('|')})`, 'gi')).map((part, index) =>
    index % 2 === 1 ? (
      <mark key={index} className="rounded-xs bg-warning-subtle text-inherit">
        {part}
      </mark>
    ) : (
      part
    ),
  );
}

export function SearchPalette({ basePath, debounce, onClose }: Props) {
  const { __ } = useTranslation();
  const listId = useId();
  const dialog = useRef<HTMLDivElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const [opener] = useState(() => document.activeElement);
  const [text, setText] = useState('');
  const [term, setTerm] = useState('');
  const [active, setActive] = useState(0);
  const { recents, add, remove, clear } = useRecentSearches();
  const wanted = text.trim().replace(/\s+/g, ' ');

  useEffect(() => {
    pauseShortcuts();
    input.current?.focus();

    return () => {
      resumeShortcuts();

      if (opener instanceof HTMLElement) {
        opener.focus();
      }
    };
  }, [opener]);

  useEffect(() => {
    const timer = setTimeout(() => {
      setTerm(wanted);
    }, debounce * 1000);

    return () => {
      clearTimeout(timer);
    };
  }, [wanted, debounce]);

  const query = useQuery({
    queryKey: queryKeys.search(term),
    queryFn: async ({ signal }) =>
      (await request<SearchResponse>(`${basePath}/api/search?q=${encodeURIComponent(term)}`, { signal })) ?? {
        groups: [],
        skipped: [],
      },
    enabled: term !== '',
  });

  const searching = wanted !== '';
  const loading = searching && (wanted !== term || query.isPending);
  const failed = searching && !loading && query.isError;
  // A 422 refuses the term itself (too long, too many words): say why, since Retry cannot help.
  const refused = query.error instanceof ApiError && query.error.status === 422 ? ((query.error.data as { message?: string } | null)?.message ?? null) : null;
  const groups = searching && !loading ? (query.data?.groups ?? []) : [];
  const options = searching ? groups.flatMap((group) => group.results) : recents;
  const current = Math.min(active, options.length - 1);
  const optionId = (index: number) => `${listId}-${String(index)}`;

  useEffect(() => {
    document.getElementById(`${listId}-${String(current)}`)?.scrollIntoView({ block: 'nearest' });
  }, [listId, current]);

  function choose(result: SearchResult) {
    add(result);
    router.visit(`${basePath}${result.url}`);
    onClose();
  }

  function onKeyDown(event: KeyboardEvent) {
    const onInput = event.target === input.current;

    if (event.key === 'Escape' || (event.key.toLowerCase() === 'k' && (event.ctrlKey || event.metaKey))) {
      // The shortcut dispatcher is paused while the palette is open, so it closes itself on mod+k.
      event.preventDefault();
      onClose();
    } else if (event.key === 'Tab') {
      const focusable = [...(dialog.current?.querySelectorAll<HTMLElement>('input, button, a[href]') ?? [])];
      const edge = event.shiftKey ? focusable[0] : focusable.at(-1);

      if (event.target === edge) {
        event.preventDefault();
        (event.shiftKey ? focusable.at(-1) : focusable[0])?.focus();
      }
    } else if (onInput && options.length > 0) {
      const jump = { ArrowDown: current + 1, ArrowUp: current - 1, Home: 0, End: options.length - 1 }[event.key];

      if (jump !== undefined) {
        event.preventDefault();
        setActive(Math.max(0, Math.min(jump, options.length - 1)));
      } else if (event.key === 'Enter' && options[current] !== undefined) {
        event.preventDefault();
        choose(options[current]);
      }
    }
  }

  function option(result: SearchResult, index: number, recent = false) {
    const active = index === current;

    return (
      // Options never take focus: the input drives them with aria-activedescendant, so the keys live there.
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <div
        key={result.url}
        id={optionId(index)}
        role="option"
        tabIndex={-1}
        aria-selected={active}
        // 44 px rows inset 6 px; on a phone, full-width 56 px rows split by rules.
        className={cn(
          'mx-1.5 flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-md px-2.5 py-1.5 max-md:mx-0 max-md:min-h-14 max-md:rounded-none max-md:border-b max-md:border-border max-md:px-4',
          active && 'bg-accent max-md:bg-transparent',
        )}
        onClick={() => {
          choose(result);
        }}
        onMouseMove={() => {
          setActive(index);
        }}
      >
        <Avatar result={result} recent={recent} />
        <span className="min-w-0">
          <span className="block truncate text-sm leading-5 font-medium max-md:text-[15px]">
            <Marked text={result.title} term={term} />
          </span>
          {/* On the highlighted row's accent ground the muted text falls under 4.5:1. */}
          {result.subTitle !== null && (
            <span className={cn('block truncate text-xs leading-4 max-md:text-[13px]', active ? 'text-foreground/80' : 'text-muted-foreground')}>
              <Marked text={result.subTitle} term={term} />
            </span>
          )}
        </span>
        {active && (
          <kbd aria-hidden="true" className={cn(kbd, 'ms-auto max-md:hidden')}>
            <CornerDownLeft className="size-3" />
          </kbd>
        )}
      </div>
    );
  }

  // The group heads: uppercase 12 px, a little apart.
  const head = 'px-4 pt-2.5 pb-1 text-xs font-semibold tracking-[0.04em] text-muted-foreground uppercase max-md:pt-3.5 max-md:pb-1.5';
  let offset = 0;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[72px] max-md:p-0">
      <button
        type="button"
        tabIndex={-1}
        aria-label={__('Close')}
        className="absolute inset-0 bg-scrim"
        onClick={onClose}
      />
      {/* The keydown handler sits on the dialog so Escape and mod+k work wherever focus is inside it. */}
      {/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
      <div
        ref={dialog}
        role="dialog"
        aria-modal="true"
        aria-label={__('Search')}
        // On a phone the palette takes the whole screen and closes with Cancel.
        className="relative flex max-h-[70vh] w-full max-w-[640px] flex-col overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-lg max-md:h-dvh max-md:max-h-none max-md:max-w-none max-md:rounded-none max-md:border-0 max-md:bg-background"
        onKeyDown={onKeyDown}
      >
        <div className="flex h-[52px] shrink-0 items-center gap-2.5 border-b border-border px-4 max-md:h-auto max-md:gap-2 max-md:py-2 max-md:ps-4 max-md:pe-2">
          {/* On a phone the field sits in a 44 px muted well. */}
          <div className="flex min-w-0 flex-1 items-center gap-2.5 max-md:h-11 max-md:gap-2 max-md:rounded-[10px] max-md:bg-muted max-md:px-3">
            <Search aria-hidden="true" strokeWidth={1.75} className="size-[18px] shrink-0 text-muted-foreground" />
            <input
              ref={input}
              type="text"
              role="combobox"
              aria-label={__('Search')}
              aria-expanded="true"
              aria-controls={`${listId}-list`}
              aria-activedescendant={options.length > 0 ? optionId(current) : undefined}
              aria-autocomplete="list"
              autoComplete="off"
              placeholder={__('Search…')}
              value={text}
              className="min-w-0 flex-1 bg-transparent text-base leading-[22px] outline-none placeholder:text-muted-foreground"
              onChange={(event) => {
                setText(event.target.value);
                setActive(0);
              }}
            />
          </div>
          <kbd aria-hidden="true" className={cn(kbd, 'max-md:hidden')}>
            {__('esc')}
          </kbd>
          <button type="button" className="h-11 shrink-0 rounded-md px-2 text-base font-medium text-link outline-none focus-visible:ring-2 focus-visible:ring-focus md:hidden" onClick={onClose}>
            {__('Cancel')}
          </button>
        </div>
        {/* Focusable, so a keyboard user can scroll a long result list. */}
        <div className="overflow-y-auto pb-2 outline-none max-md:flex-1" role="region" tabIndex={0} aria-label={__('Search results')}>
          {!searching && recents.length > 0 && (
            <div className={cn(head, 'flex items-center justify-between')}>
              <span>{__('Recent searches')}</span>
              <button type="button" className="rounded-sm text-xs font-medium tracking-normal text-link normal-case outline-none hover:text-link-hover focus-visible:ring-2 focus-visible:ring-focus" onClick={clear}>
                {__('Clear')}
              </button>
            </div>
          )}
          {loading && (
            <div aria-busy="true" className="flex flex-col gap-3.5 p-4">
              <p role="status" className="sr-only">
                {__('Searching…')}
              </p>
              <div data-skeleton="" className="h-3 w-[30%] animate-pulse rounded bg-skeleton" />
              {['60%', '45%'].map((width) => (
                <div key={width} data-skeleton="" className="flex items-center gap-3">
                  <div className="size-7 animate-pulse rounded-lg bg-skeleton" />
                  <div className="h-3 animate-pulse rounded bg-skeleton" style={{ width }} />
                </div>
              ))}
            </div>
          )}
          {failed && refused !== null && (
            <p role="alert" className="px-4 py-3 text-sm">
              {refused}
            </p>
          )}
          {failed && refused === null && (
            <div className="flex items-center justify-between gap-2 px-4 py-3 text-sm">
              <span role="alert">{__('Search failed')}</span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  void query.refetch();
                }}
              >
                {__('Retry')}
              </Button>
            </div>
          )}
          {searching && !loading && !failed && groups.length === 0 && (
            <div className="flex flex-col gap-1 px-4 pt-5 pb-3 text-center">
              <p className="text-sm font-medium">{__('No results for ":term"', { term: wanted })}</p>
              <p className="text-[13px] text-muted-foreground">{__('Check the spelling, or search one word of a name.')}</p>
            </div>
          )}
          <div id={`${listId}-list`} role="listbox" aria-label={__('Search')}>
            {searching
              ? groups.map((group) => {
                  const start = offset;

                  offset += group.results.length;

                  const all = `${basePath}/resources/${group.resourceName}?q=${encodeURIComponent(wanted)}`;

                  return (
                    <div key={group.resourceName} role="group" aria-label={group.label}>
                      <div aria-hidden="true" className={head}>
                        {group.label}
                      </div>
                      {group.results.map((result, index) => option(result, start + index))}
                      {group.count && (
                        <a
                          href={all}
                          className="mx-1.5 flex min-h-9 items-center rounded-md px-2.5 text-[13px] font-medium text-link hover:bg-accent hover:text-link-hover max-md:mx-0 max-md:min-h-12 max-md:rounded-none max-md:px-4 max-md:text-[15px]"
                          onClick={(event) => {
                            event.preventDefault();
                            router.visit(all);
                            onClose();
                          }}
                        >
                          {__('Show all :count :resource', {
                            count: `${group.count.value.toLocaleString()}${group.count.capped ? '+' : ''}`,
                            resource: group.label.toLowerCase(),
                          })}
                        </a>
                      )}
                    </div>
                  );
                })
              : recents.map((result, index) => (
                  <div key={result.url} role="presentation" className="flex items-center pe-1.5">
                    {option(result, index, true)}
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-7"
                      aria-label={__('Remove :title', { title: result.title })}
                      onClick={() => {
                        remove(result.url);
                      }}
                    >
                      <X aria-hidden="true" className="size-3" />
                    </Button>
                  </div>
                ))}
          </div>
          {searching && !loading && !failed && (query.data?.skipped.length ?? 0) > 0 && (
            <p className="px-4 pt-2 text-xs text-muted-foreground">{__('Some results are missing')}</p>
          )}
        </div>
        {/* The key hints show once there is a row to move to. */}
        {searching && options.length > 0 && (
          <div aria-hidden="true" className="flex h-10 shrink-0 items-center gap-4 border-t border-border px-4 text-xs text-muted-foreground max-md:hidden">
            <span className="inline-flex items-center gap-1.5">
              <kbd className={kbd}>
                <ArrowUp className="size-3" />
              </kbd>
              <kbd className={kbd}>
                <ArrowDown className="size-3" />
              </kbd>
              {__('Move')}
            </span>
            <span className="inline-flex items-center gap-1.5">
              <kbd className={kbd}>
                <CornerDownLeft className="size-3" />
              </kbd>
              {__('Open')}
            </span>
          </div>
        )}
      </div>
    </div>
  );
}
