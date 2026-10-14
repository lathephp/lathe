/**
 * The query pairs of a filters:changed payload, in the filter[key] shapes the index reads:
 * filter[key]=v, filter[key][]=v and filter[key][side]=v. Anything else gives no pairs.
 */
export function filterParams(payload: unknown): [string, string][] {
  const filters = (payload as { filters?: unknown } | null | undefined)?.filters;

  if (typeof filters !== 'object' || filters === null) {
    return [];
  }

  return Object.entries(filters as Record<string, unknown>).flatMap(([key, value]): [string, string][] => {
    if (typeof value === 'string') {
      return [[`filter[${key}]`, value]];
    }

    if (Array.isArray(value)) {
      return value.map((item): [string, string] => [`filter[${key}][]`, String(item)]);
    }

    if (typeof value === 'object' && value !== null) {
      return Object.entries(value).map(([side, item]): [string, string] => [`filter[${key}][${side}]`, String(item)]);
    }

    return [];
  });
}
