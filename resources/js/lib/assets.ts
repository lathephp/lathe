let entryHash: string | null = null;
let stale = false;
const listeners = new Set<() => void>();

/** The build hash in the entry module's own URL, /lathe/assets/{hash}/app-{name}.js. */
export function assetHashFromUrl(url: string): string | null {
  return /\/assets\/([^/]+)\/[^/]+\.js$/.exec(url)?.[1] ?? null;
}

/** The panel sets the hash of the build it runs once, before the first page renders. */
export function setEntryHash(hash: string | null): void {
  entryHash = hash;
}

export function entryAssetHash(): string | null {
  return entryHash;
}

/**
 * A page chunk of the loaded build failed to load: the server has moved on to a new build,
 * so the old build's files are gone.
 */
export function markBuildStale(): void {
  stale = true;
  listeners.forEach((listener) => {
    listener();
  });
}

export function isBuildStale(): boolean {
  return stale;
}

export function subscribeToStale(listener: () => void): () => void {
  listeners.add(listener);

  return () => {
    listeners.delete(listener);
  };
}

/** For tests: forget a stale build. */
export function resetBuildStale(): void {
  stale = false;
}
