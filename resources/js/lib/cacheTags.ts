import { router } from '@inertiajs/react';

/** Drops the prefetched pages of the resource a write URL names, so a hovered link never opens a stale page. */
export function flushResourceOf(url: string): void {
  const match = /\/resources\/([^/?#]+)/.exec(url);

  if (match?.[1] !== undefined) {
    router.flushByCacheTags(`resource:${match[1]}`);
  }
}
