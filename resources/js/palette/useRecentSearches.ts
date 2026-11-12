import { useState } from 'react';
import { claimStorage } from '../lib/owner';
import type { SearchResult } from './results';

const storageKey = 'lathe-recent-searches';
const limit = 5;

/** Drops the recent searches when the signed-in user is not the one who made them. */
export function claimRecentSearches(email: string | null): void {
  claimStorage(() => localStorage, 'lathe-recent-searches-owner', email, (store) => {
    store.removeItem(storageKey);
  });
}

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
