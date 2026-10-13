import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export type FilterValue = string | string[] | Record<string, string>;

/** The index URL, one key per param. */
export interface IndexUrlState {
  q?: string;
  sort?: string;
  page?: number;
  per_page?: number;
  filter?: Record<string, FilterValue>;
  tab?: string;
  saved_tab?: string;
  group?: string;
  trashed?: string;
}

export interface IndexDefaults {
  /** The Resource's first per-page option, which the URL leaves out. */
  perPage: number;
}

type Query = Exclude<Parameters<typeof router.get>[1], undefined>;

const stringParams = ['q', 'sort', 'tab', 'saved_tab', 'group', 'trashed'] as const;

// A skeleton shorter than this flickers.
const minimumSkeletonMs = 300;

export function parseQuery(search: string): IndexUrlState {
  const state: IndexUrlState = {};
  const params = new URLSearchParams(search);

  for (const param of stringParams) {
    const value = params.get(param);

    if (value !== null && value !== '') {
      state[param] = value;
    }
  }

  for (const param of ['page', 'per_page'] as const) {
    const value = Number(params.get(param));

    if (Number.isInteger(value) && value >= 1) {
      state[param] = value;
    }
  }

  for (const [name, value] of params) {
    const match = /^filter\[([^\]]+)\](?:\[([^\]]*)\])?$/.exec(name);
    const key = match?.[1];

    if (key === undefined) {
      continue;
    }

    const filter = (state.filter ??= {});
    const inner = match?.[2];

    if (inner === undefined) {
      filter[key] = value;
    } else if (inner === '') {
      const list = filter[key];
      filter[key] = [...(Array.isArray(list) ? list : []), value];
    } else {
      const object = filter[key];
      filter[key] = { ...(typeof object === 'object' && !Array.isArray(object) ? object : {}), [inner]: value };
    }
  }

  return state;
}

function compactFilter(filter: Record<string, FilterValue>): Record<string, FilterValue> {
  const kept: Record<string, FilterValue> = {};

  for (const [key, value] of Object.entries(filter)) {
    if (typeof value === 'string') {
      if (value !== '') {
        kept[key] = value;
      }
    } else if (Array.isArray(value)) {
      if (value.length > 0) {
        kept[key] = value;
      }
    } else {
      const entries = Object.entries(value).filter(([, side]) => side !== '');

      if (entries.length > 0) {
        kept[key] = Object.fromEntries(entries);
      }
    }
  }

  return kept;
}

/** The data for router.get: only the values that are not the default. */
export function toQuery(state: IndexUrlState, defaults: IndexDefaults): Query {
  const query: Query = {};

  for (const param of stringParams) {
    const value = state[param];

    if (value !== undefined && value !== '') {
      query[param] = value;
    }
  }

  if (state.page !== undefined && state.page > 1) {
    query.page = state.page;
  }

  if (state.per_page !== undefined && state.per_page !== defaults.perPage) {
    query.per_page = state.per_page;
  }

  const filter = compactFilter(state.filter ?? {});

  if (Object.keys(filter).length > 0) {
    query.filter = filter;
  }

  return query;
}

/** Merge a change into the state; any change besides the page itself starts again at page 1. */
export function applyChanges(state: IndexUrlState, changes: IndexUrlState): IndexUrlState {
  const next = { ...state, ...changes };

  if (!('page' in changes)) {
    delete next.page;
  }

  return next;
}

export function useIndexState(defaults: IndexDefaults) {
  const page = usePage();
  const [path = page.url, search = ''] = page.url.split('?');
  const state = parseQuery(search);
  const [loading, setLoading] = useState(false);

  function visit(changes: IndexUrlState) {
    let startedAt = 0;

    router.get(path, toQuery(applyChanges(state, changes), defaults), {
      preserveState: true,
      preserveScroll: true,
      onStart: () => {
        startedAt = Date.now();
        setLoading(true);
      },
      onFinish: () => {
        setTimeout(
          () => {
            setLoading(false);
          },
          Math.max(0, minimumSkeletonMs - (Date.now() - startedAt)),
        );
      },
    });
  }

  return { state, visit, loading };
}
