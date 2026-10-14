import { QueryClient } from '@tanstack/react-query';

export { QueryClientProvider, useQuery, useQueryClient } from '@tanstack/react-query';

/** The one client for reads after first paint. A read stays fresh for 60 s. */
export const queryClient = new QueryClient({
  defaultOptions: { queries: { staleTime: 60_000, retry: false } },
});

/** Keys for the panel's reads, so one request shares one cache entry. */
export const queryKeys = {
  search: (term: string) => ['search', term] as const,
  filterOptions: (resource: string, filter: string, term: string, page: number) =>
    ['filter-options', resource, filter, term, page] as const,
  count: (resource: string, params: Record<string, unknown>) => ['count', resource, params] as const,
  // The reads of one Resource share the prefix ['resource', resource], so an action can mark them all stale.
  resourceIndex: (resource: string, params: Record<string, unknown>) => ['resource', resource, 'index', params] as const,
  resourceDetail: (resource: string, id: string | number) => ['resource', resource, 'detail', String(id)] as const,
  resourceActions: (resource: string, params: Record<string, unknown>) => ['resource', resource, 'actions', params] as const,
  widgets: (path: string, params: Record<string, unknown>) => ['widgets', path, params] as const,
};
