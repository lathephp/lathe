import type { ComponentType } from 'react';

// Each page is its own lazy chunk.
export const pages = import.meta.glob<{ default: ComponentType }>('../pages/**/*.tsx');

/** Starts loading a page's chunk, so an instant visit to it has nothing to wait for. */
export function preloadPage(name: string): void {
  void pages[`../pages/${name}.tsx`]?.().catch(() => undefined);
}
