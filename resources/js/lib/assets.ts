let entryHash: string | null = null;

/** The build hash in the entry module's own URL, /lathe/assets/{hash}/assets/app-{name}.js. */
export function assetHashFromUrl(url: string): string | null {
  return /\/assets\/([^/]+)\/assets\/[^/]+$/.exec(url)?.[1] ?? null;
}

/** The panel sets the hash of the build it runs once, before the first page renders. */
export function setEntryHash(hash: string | null): void {
  entryHash = hash;
}

export function entryAssetHash(): string | null {
  return entryHash;
}
