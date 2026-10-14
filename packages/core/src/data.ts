import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { useEffect, useState, useSyncExternalStore } from 'react';
import { emit } from './events';
import { useLathe } from './hooks';
import { ApiError, request } from './http';
import { queryKeys } from './query';
import { createStore } from './store';
import type { ActionData, ResourceDetailResponse, ResourceIndexResponse, WidgetData } from './types';

/** What every data hook returns. */
export interface QueryState<T> {
  data: T | undefined;
  error: Error | null;
  isLoading: boolean;
  isFetching: boolean;
  refetch: () => Promise<unknown>;
}

type Params = Record<string, string | number>;

/** Where cards come from: a dashboard, or a Resource and one of its lenses. */
export type WidgetSource = { dashboard: string } | { resource: string; lens?: string };

/** What a card's value endpoint answers. */
export interface WidgetValueResponse {
  name: string;
  uriKey: string;
  component: string;
  value: unknown;
}

export interface ResourceActionList {
  actions: ActionData[];
  pivotActions: { name: string; actions: ActionData[] }[];
  counts: Record<string, number>;
}

/** What POST /action answers. */
export interface ActionRun {
  result: unknown;
  permitted: number;
  skipped: number;
  failed: number;
  errors: { id: string | number | null; message: string }[];
  /** Set when the run went to the queue. */
  bulk_run?: { id: string; total: number };
}

function withQuery(path: string, params: Params = {}): string {
  const query = new URLSearchParams(Object.entries(params).map(([key, value]) => [key, String(value)])).toString();

  return query === '' ? path : `${path}?${query}`;
}

async function body<T>(url: string, options: Parameters<typeof request>[1]): Promise<T> {
  const data = await request<T>(url, options);

  if (data === null) {
    throw new ApiError(204, null, 'The server answered nothing.');
  }

  return data;
}

function useRead<T, R = T>(key: readonly unknown[], path: string, params?: Params, pick?: (data: T) => R): QueryState<R> {
  const { apiPath } = useLathe();
  const { data, error, isLoading, isFetching, refetch }: UseQueryResult<R> = useQuery({
    queryKey: key,
    queryFn: async ({ signal }) => {
      const read = await body<T>(withQuery(`${apiPath}/${path}`, params), { signal });

      return pick === undefined ? (read as unknown as R) : pick(read);
    },
  });

  return { data, error, isLoading, isFetching, refetch };
}

function widgetsPath(source: WidgetSource): string {
  if ('dashboard' in source) {
    return `dashboards/${source.dashboard}/widgets`;
  }

  return source.lens === undefined ? `resources/${source.resource}/widgets` : `resources/${source.resource}/lenses/${source.lens}/widgets`;
}

/** One page of a Resource's index, read with the same parameters as its URL: page, sort, q, filters. */
export function useResourceIndex(resource: string, params?: Params): QueryState<ResourceIndexResponse> {
  return useRead(queryKeys.resourceIndex(resource, params ?? {}), `resources/${resource}`, params);
}

/** One record and its detail fields. */
export function useResourceDetail(resource: string, id: string | number): QueryState<ResourceDetailResponse> {
  return useRead(queryKeys.resourceDetail(resource, id), `resources/${resource}/${String(id)}/peek`);
}

/** The actions of a Resource; pass resourceId for the actions of one record. */
export function useResourceActions(resource: string, params?: Params): QueryState<ResourceActionList> {
  return useRead(queryKeys.resourceActions(resource, params ?? {}), `resources/${resource}/actions`, params);
}

/** Runs an action on a selection, then marks the Resource's reads stale and emits actions:ran. */
export function useExecuteAction(resource: string) {
  const { apiPath } = useLathe();
  const client = useQueryClient();
  const { mutateAsync, data, error, isPending } = useMutation({
    mutationFn: (input: { action: string; selection: { mode: 'ids'; ids: (string | number)[] } | { mode: 'all'; [key: string]: unknown }; fields?: Record<string, unknown> }) =>
      body<ActionRun>(`${apiPath}/resources/${resource}/action`, { method: 'POST', body: { fields: {}, ...input }, toastOnError: false }),
    onSuccess: async () => {
      emit('actions:ran', { resource });
      await client.invalidateQueries({ queryKey: ['resource', resource] });
    },
  });

  return { execute: mutateAsync, data, error, isPending };
}

/**
 * Saves one field of a record in place. The value shows at once while the save runs; the
 * reply's cells replace the row in every cached index page, so nothing refetches.
 */
export function useInlineField(resource: string, id: string | number, field: string) {
  const { apiPath } = useLathe();
  const client = useQueryClient();
  const [pending, setPending] = useState<{ value: unknown } | null>(null);
  const { mutateAsync, error, isPending } = useMutation({
    mutationFn: (value: unknown) => {
      const key = ['resource', resource, 'index'];
      const row = client
        .getQueriesData<ResourceIndexResponse>({ queryKey: key })
        .flatMap(([, page]) => page?.table.rows ?? [])
        .find((candidate) => String(candidate.id) === String(id));

      return body<{ fields: ResourceIndexResponse['table']['rows'][number]['cells']; updatedAt?: string | null }>(
        `${apiPath}/resources/${resource}/${String(id)}/field/${field}`,
        { method: 'PATCH', body: { value, _updated_at: row?.updatedAt ?? undefined }, toastOnError: false },
      );
    },
    onMutate: (value) => {
      setPending({ value });
    },
    onSuccess: (reply) => {
      client.setQueriesData<ResourceIndexResponse>({ queryKey: ['resource', resource, 'index'] }, (page) =>
        page === undefined
          ? page
          : {
              ...page,
              table: {
                ...page.table,
                rows: page.table.rows.map((row) =>
                  String(row.id) === String(id) ? { ...row, cells: { ...row.cells, ...reply.fields }, updatedAt: reply.updatedAt ?? row.updatedAt } : row,
                ),
              },
            },
      );
    },
    onSettled: () => {
      setPending(null);
    },
  });

  return { save: mutateAsync, pending, error, isSaving: isPending };
}

/** The cards of a dashboard, a Resource or a lens; pass resourceId for a detail page's cards. */
export function useWidgetList(source: WidgetSource, params?: Params): QueryState<WidgetData[]> {
  return useRead(queryKeys.widgets(widgetsPath(source), params ?? {}), widgetsPath(source), params, (data: { widgets: WidgetData[] }) => data.widgets);
}

/** The value of one card. */
export function useWidgetValue(source: WidgetSource, widget: string, params?: Params): QueryState<WidgetValueResponse> {
  const path = `${widgetsPath(source)}/${widget}`;

  return useRead(queryKeys.widgets(path, params ?? {}), path, params);
}

/** Set by the panel while an Inertia visit runs; prefetches and background polls do not count. */
export const navigating = createStore(false);

/** True while the panel loads the next page. */
export function useNavigationProgress(): boolean {
  return useSyncExternalStore(navigating.subscribe, navigating.get);
}

/** The value, once it has stopped changing for delay milliseconds. */
export function useDebounce<T>(value: T, delay: number): T {
  const [settled, setSettled] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => {
      setSettled(value);
    }, delay);

    return () => {
      clearTimeout(timer);
    };
  }, [value, delay]);

  return settled;
}
