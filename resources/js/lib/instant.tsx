import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { useEffect, useState, type ReactNode } from 'react';

// Instant pages: a link shows its page before the server answers, and a write drops the pages
// prefetched for its resource. The pieces live in one module, so the first load fetches one chunk.

/**
 * The props an instant visit renders with: the shell's shared props and what the link knows, such as
 * the title. The props the server sends once are left for Inertia to carry over, since only then does
 * it keep them for a prefetched answer that leaves them out.
 */
export function instantProps(seed: { title: string } & Record<string, unknown>, once: Record<string, { prop: PropertyKey }> = {}) {
  const kept = new Set<PropertyKey>(Object.values(once).map((entry) => entry.prop));

  return (_current: Record<string, unknown>, shared: Record<string, unknown>) => ({
    ...Object.fromEntries(Object.entries(shared).filter(([key]) => !kept.has(key))),
    ...seed,
  });
}

/** Drops the prefetched pages of the resource a write URL names, so a hovered link never opens a stale page. */
export function flushResourceOf(url: string): void {
  const match = /\/resources\/([^/?#]+)/.exec(url);

  if (match?.[1] !== undefined) {
    router.flushByCacheTags(`resource:${match[1]}`);
  }
}

// Every write through the panel's client drops its resource's prefetched pages.
__panel.setWriteListener(flushResourceOf);

/** The fill of a skeleton bar, which pulses unless reduced motion is asked for. */
export const skeletonBar = 'animate-pulse rounded-md bg-skeleton motion-reduce:animate-none';

/**
 * Shows nothing for the first 100 ms, so an answer that comes quickly never flashes a skeleton.
 * An instant visit's page shows its title and the shape of its layout through it, so the old
 * page is never left looking ready.
 */
export function AfterDelay({ children }: { children: ReactNode }) {
  const [shown, setShown] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      setShown(true);
    }, 100);

    return () => {
      clearTimeout(timer);
    };
  }, []);

  return shown ? children : null;
}
