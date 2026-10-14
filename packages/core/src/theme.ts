import { useSyncExternalStore } from 'react';

/** The choices the user menu offers: a fixed scheme, or the OS's. */
export type Appearance = 'light' | 'dark' | 'system';

const storageKey = 'lathe-appearance';
const listeners = new Set<() => void>();

function current(): Appearance {
  const value = document.documentElement.dataset.latheAppearance;

  return value === 'light' || value === 'dark' ? value : 'system';
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

function setAppearance(appearance: Appearance): void {
  const root = document.documentElement;
  const dark = appearance === 'dark' || (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

  // The page's own state, so a blocked storage still keeps the choice for the session.
  root.dataset.latheAppearance = appearance;
  root.classList.toggle('dark', dark);
  root.classList.toggle('light', !dark);

  try {
    localStorage.setItem(storageKey, appearance);
  } catch {
    // Blocked storage: the choice lasts until the page unloads.
  }

  listeners.forEach((listener) => {
    listener();
  });
}

/** The panel's appearance and a setter that applies the choice to the page and remembers it. */
export function useTheme(): { appearance: Appearance; setAppearance: (appearance: Appearance) => void } {
  const appearance = useSyncExternalStore(subscribe, current);

  return { appearance, setAppearance };
}
