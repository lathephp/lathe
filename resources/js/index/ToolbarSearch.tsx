import { addShortcut, useTranslation } from '@lathephp/core';
import { Search } from 'lucide-react';
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

  // Open at 260 px; on a narrow screen only its icon shows until it has focus or text.
  const name = __('Search :resource', { resource: label.toLowerCase() });

  return (
    <span className="lathe-search">
      <Search aria-hidden="true" className="size-3.5" />
      <input
        ref={input}
        type="search"
        value={term}
        placeholder={name}
        aria-label={name}
        aria-keyshortcuts="/"
        onChange={(event) => {
          const next = event.target.value;
          setTerm(next);
          clearTimeout(timer.current);
          timer.current = setTimeout(() => {
            onSearch(next.trim());
          }, debounce * 1000);
        }}
      />
      <kbd aria-hidden="true">/</kbd>
    </span>
  );
}
