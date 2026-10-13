import { useState } from 'react';
import type { SearchResult } from './results';

const storageKey = 'lathe-recent-searches';
const limit = 5;

function read(): SearchResult[] {
  try {
    const parsed: unknown = JSON.parse(localStorage.getItem(storageKey) ?? '[]');

    return Array.isArray(parsed) ? (parsed as SearchResult[]).filter((entry) => typeof entry.url === 'string').slice(0, limit) : [];
  } catch {
    return [];
  }
}

function write(entries: SearchResult[]): void {
  try {
    localStorage.setItem(storageKey, JSON.stringify(entries));
  } catch {
    // Storage is blocked or full: the recents then last only while the palette is open.
  }
}

/** The last results a person opened, newest first, one per URL. Blocked storage means none. */
export function useRecentSearches() {
  const [recents, setRecents] = useState(read);

  function update(next: SearchResult[]) {
    setRecents(next);
    write(next);
  }

  return {
    recents,
    add: (result: SearchResult) => {
      update([result, ...read().filter((entry) => entry.url !== result.url)].slice(0, limit));
    },
    remove: (url: string) => {
      update(recents.filter((entry) => entry.url !== url));
    },
    clear: () => {
      update([]);
    },
  };
}
