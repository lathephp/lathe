import { request, useLathe, useQuery } from '@lathephp/core';
import type { Column, Count, Row } from '../index/types';
import { toQuery, type IndexUrlState } from '../index/useIndexState';

/** A row of a relation panel: the related record, and its pivot row on a pivot relation. */
export interface RelationRow extends Row {
  related_id: number | string;
  pivot_id?: number | string;
}

/** The reply of GET resources/{resource}/{id}/{relationship}. */
export interface RelationIndex {
  columns: Column[];
  sortKeys: string[];
  rows: RelationRow[];
  count: Count | null;
  page: number;
  perPage: number;
  perPageOptions: number[];
  hasMore: boolean;
  /** Whether the related Resource has search columns or Scout; without them a term changes nothing. */
  searchable: boolean;
}

export interface RelateFlags {
  add: boolean;
  attach: boolean;
  attachAny: boolean;
  detach: boolean;
}

/** The query string of the panel state; a filter value nests one level, `filter[quantity][min]=5`. */
export function searchParams(state: IndexUrlState): string {
  const params = new URLSearchParams();

  // A per-page default of 0 never matches, so a chosen per_page is always sent.
  for (const [key, value] of Object.entries(toQuery(state, { perPage: 0 }))) {
    if (typeof value === 'string' || typeof value === 'number') {
      params.append(key, String(value));
      continue;
    }

    for (const [name, filter] of Object.entries(value as Record<string, string | string[] | Record<string, string>>)) {
      if (typeof filter === 'string') {
        params.append(`${key}[${name}]`, filter);
      } else if (Array.isArray(filter)) {
        filter.forEach((item) => {
          params.append(`${key}[${name}][]`, item);
        });
      } else {
        Object.entries(filter).forEach(([side, item]) => {
          params.append(`${key}[${name}][${side}]`, item);
        });
      }
    }
  }

  const query = params.toString();

  return query === '' ? '' : `?${query}`;
}

/** The rows of one panel. They go stale at once, so a panel opened again after a create shows the new row. */
export function useRelationIndex(uriKey: string, id: string | number, relationship: string, state: IndexUrlState) {
  const { apiPath } = useLathe();
  const url = `${apiPath}/resources/${uriKey}/${String(id)}/${relationship}${searchParams(state)}`;

  return useQuery({
    queryKey: ['relation', uriKey, String(id), relationship, url],
    queryFn: () => request<RelationIndex>(url),
    staleTime: 0,
    placeholderData: (previous) => previous,
  });
}

/** The add and attach flags of every relation field of the record, one request shared by every panel. */
export function useRelateFlags(uriKey: string, id: string | number) {
  const { apiPath } = useLathe();

  return useQuery({
    queryKey: ['relate-authorization', uriKey, String(id)],
    queryFn: () => request<Record<string, RelateFlags>>(`${apiPath}/resources/${uriKey}/relate-authorization?resourceId=${encodeURIComponent(String(id))}`),
  });
}
