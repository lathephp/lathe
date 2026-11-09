import { queryClient, queryKeys, request, useQuery } from '@lathephp/core';
import type { WidgetValue } from './types';

/** One read of a card's value: its endpoint with the range, the filters and the host's params. */
function widgetQuery(url: string, range: string | null, filters: [string, string][], params: Record<string, string> = {}) {
  const query = new URLSearchParams([...(range === null ? [] : [['range', range]]), ...filters, ...Object.entries(params)]).toString();

  return {
    queryKey: queryKeys.widgets(url, { range, filters, params }),
    queryFn: () => request<WidgetValue>(query === '' ? url : `${url}?${query}`),
  };
}

/**
 * A card's value through the query cache, so a card mounted again within 60 s shows its value
 * with no request. The singleton client is passed, so a card needs no provider above it.
 */
export function useWidgetValue(url: string, range: string | null, filters: [string, string][], params: Record<string, string> | undefined, options: { enabled: boolean; poll: number }) {
  const query = useQuery(
    {
      ...widgetQuery(url, range, filters, params),
      enabled: options.enabled,
      // The last value stays up while a new range or filter set loads.
      placeholderData: (previous) => previous,
      refetchInterval: options.poll > 0 ? options.poll * 1000 : false,
      // A card refetches on its own triggers only: Refresh, a range, a poll or an event.
      refetchOnWindowFocus: false,
    },
    queryClient,
  );

  return {
    value: query.data ?? undefined,
    failed: query.isError,
    /** Fetches the value for this range and filters even when the cache holds a fresh one; settles either way. */
    fetchNow: (nextRange: string | null, nextFilters: [string, string][]) =>
      queryClient.query({ ...widgetQuery(url, nextRange, nextFilters, params), staleTime: 0 }).then(
        () => undefined,
        () => undefined,
      ),
  };
}
