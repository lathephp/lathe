/** One row of the search endpoint. `url` is relative to the panel. */
export interface SearchResult {
  resourceTitle: string;
  title: string;
  subTitle: string | null;
  resourceName: string;
  resourceId: string | number;
  avatar: string | null;
  rounded: boolean;
  url: string;
}

export interface SearchGroup {
  resourceName: string;
  label: string;
  results: SearchResult[];
}

export interface SearchResponse {
  groups: SearchGroup[];
  skipped: string[];
}

/** The two letters shown when a row has no image: both words' first letters, else the first two characters. */
export function initials(title: string): string {
  const words = title.trim().split(/\s+/);
  const letters = words.length > 1 ? `${words[0]?.[0] ?? ''}${words[1]?.[0] ?? ''}` : title.trim().slice(0, 2);

  return letters.toUpperCase();
}
