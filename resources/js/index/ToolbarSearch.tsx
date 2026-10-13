import { addShortcut, useTranslation } from '@lathephp/core';
import { useEffect, useRef, useState } from 'react';

interface ToolbarSearchProps {
  label: string;
  value: string;
  /** Seconds to wait after the last keystroke. */
  debounce: number;
  onSearch: (q: string) => void;
}

export function ToolbarSearch({ label, value, debounce, onSearch }: ToolbarSearchProps) {
  const { __ } = useTranslation();
  const [term, setTerm] = useState(value);
  const [seen, setSeen] = useState(value);
  const input = useRef<HTMLInputElement>(null);
  const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

  // The page keeps its state across visits, so a q changed elsewhere (Clear filters) is copied in.
  // The box's own search lands as its trimmed text and leaves the text alone.
  if (value !== seen) {
    setSeen(value);

    if (value !== term.trim()) {
      setTerm(value);
    }
  }

  useEffect(
    () =>
      addShortcut('/', (event) => {
        // Keeps the slash out of the box it focuses.
        event.preventDefault();
        input.current?.focus();
      }),
    [],
  );

  useEffect(
    () => () => {
      clearTimeout(timer.current);
    },
    [],
  );

  return (
    <input
      ref={input}
      type="search"
      value={term}
      placeholder={__('Search')}
      aria-label={__('Search :resource', { resource: label.toLowerCase() })}
      aria-keyshortcuts="/"
      className="h-9 w-full max-w-xs rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
      onChange={(event) => {
        const next = event.target.value;
        setTerm(next);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
          onSearch(next.trim());
        }, debounce * 1000);
      }}
    />
  );
}
