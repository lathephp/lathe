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
};
