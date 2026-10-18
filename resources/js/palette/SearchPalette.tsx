import { router } from '@inertiajs/react';
import { ApiError, pauseShortcuts, queryKeys, request, resumeShortcuts, useQuery, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { Search, X } from 'lucide-react';
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

function Avatar({ result }: { result: SearchResult }) {
  const shape = result.rounded ? 'rounded-full' : 'rounded-md';

  return result.avatar === null ? (
    <span
      aria-hidden="true"
      className={cn('flex size-8 shrink-0 items-center justify-center bg-muted text-xs font-medium', shape)}
    >
      {initials(result.title)}
    </span>
  ) : (
    <img src={result.avatar} alt="" className={cn('size-8 shrink-0 object-cover', shape)} />
  );
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
      <mark key={index} className="rounded-sm bg-primary-subtle text-primary-subtle-foreground">
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

  function option(result: SearchResult, index: number) {
    return (
      // Options never take focus: the input drives them with aria-activedescendant, so the keys live there.
      // eslint-disable-next-line jsx-a11y/click-events-have-key-events
      <div
        key={result.url}
        id={optionId(index)}
        role="option"
        tabIndex={-1}
        aria-selected={index === current}
        className={cn(
          'flex min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-md px-2 py-2',
          index === current && 'bg-muted',
        )}
        onClick={() => {
          choose(result);
        }}
        onMouseMove={() => {
          setActive(index);
        }}
      >
        <Avatar result={result} />
        <span className="min-w-0">
          <span className="block truncate text-sm font-medium">
            <Marked text={result.title} term={term} />
          </span>
          {/* On the highlighted row's muted background the muted text falls under 4.5:1. */}
          {result.subTitle !== null && (
            <span className={cn('block truncate text-xs', index === current ? 'text-foreground/80' : 'text-muted-foreground')}>
              <Marked text={result.subTitle} term={term} />
            </span>
          )}
        </span>
      </div>
    );
  }

  let offset = 0;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[10vh] max-md:p-0">
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
        className="relative flex max-h-[70vh] w-full max-w-[640px] flex-col overflow-hidden rounded-lg border border-border bg-background text-foreground shadow-lg max-md:h-dvh max-md:max-h-none max-md:max-w-none max-md:rounded-none max-md:border-0"
        onKeyDown={onKeyDown}
      >
        <div className="flex items-center gap-2 border-b border-border px-3">
          <Search aria-hidden="true" className="size-4 shrink-0 text-muted-foreground" />
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
            className="h-12 min-w-0 flex-1 bg-transparent text-sm outline-none"
            onChange={(event) => {
              setText(event.target.value);
              setActive(0);
            }}
          />
          <button type="button" className="h-11 shrink-0 rounded-md px-2 text-sm font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring md:hidden" onClick={onClose}>
            {__('Cancel')}
          </button>
        </div>
        {/* Focusable, so a keyboard user can scroll a long result list. */}
        <div className="overflow-y-auto p-2" role="region" tabIndex={0} aria-label={__('Search results')}>
          {!searching && recents.length > 0 && (
            <div className="flex items-center justify-between px-2 pb-1 text-xs text-muted-foreground">
              <span>{__('Recent searches')}</span>
              <Button variant="ghost" size="sm" onClick={clear}>
                {__('Clear')}
              </Button>
            </div>
          )}
          {loading && (
            <div aria-busy="true" className="space-y-2">
              <p role="status" className="sr-only">
                {__('Searching…')}
              </p>
              {[0, 1, 2].map((slot) => (
                <div key={slot} data-skeleton="" className="h-12 animate-pulse rounded-md bg-muted" />
              ))}
            </div>
          )}
          {failed && refused !== null && (
            <p role="alert" className="px-2 py-3 text-sm">
              {refused}
            </p>
          )}
          {failed && refused === null && (
            <div className="flex items-center justify-between gap-2 px-2 py-3 text-sm">
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
            <p className="px-2 py-6 text-center text-sm text-muted-foreground">
              {__('No results for ":term"', { term: wanted })}
            </p>
          )}
          <div id={`${listId}-list`} role="listbox" aria-label={__('Search')}>
            {searching
              ? groups.map((group) => {
                  const start = offset;

                  offset += group.results.length;

                  const all = `${basePath}/resources/${group.resourceName}?q=${encodeURIComponent(wanted)}`;

                  return (
                    <div key={group.resourceName} role="group" aria-label={group.label}>
                      <div aria-hidden="true" className="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">
                        {group.label}
                      </div>
                      {group.results.map((result, index) => option(result, start + index))}
                      {group.count && (
                        <a
                          href={all}
                          className="block rounded-md px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
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
                  <div key={result.url} role="presentation" className="flex items-center">
                    {option(result, index)}
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
            <p className="px-2 pt-2 text-xs text-muted-foreground">{__('Some results are missing')}</p>
          )}
        </div>
      </div>
    </div>
  );
}
