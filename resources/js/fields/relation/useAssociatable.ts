import { __panel, request, toast, useLathe, useTranslation } from '@lathephp/core';
import { useEffect, useState, useSyncExternalStore } from 'react';
import type { FieldSchema } from '../types';

/** One record a relation picker offers. */
export interface AssociatableItem {
  value: string | number;
  display: string;
  subtitle?: string;
}

interface AssociatablePage {
  items: AssociatableItem[];
  has_more: boolean;
}

interface Options {
  /** Requests go out only while the control is open. */
  open: boolean;
  term: string;
  withTrashed: boolean;
}

/**
 * The options of a relation field, one server page at a time. Opening asks at once, a typed
 * term waits the field's debounce (500 ms by default), and a failure toasts with Retry.
 */
export function useAssociatable(field: FieldSchema, { open, term, withTrashed }: Options) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const shared = useSyncExternalStore(__panel.sharedProps.subscribe, __panel.sharedProps.get);
  const [query, setQuery] = useState(term.trim());
  // A new term or With Trashed starts again at page 1.
  const key = `${query}|${String(withTrashed)}`;
  const [paging, setPaging] = useState({ key, page: 1 });
  const page = paging.key === key ? paging.page : 1;
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<{ items: AssociatableItem[]; hasMore: boolean; loaded: boolean }>({ items: [], hasMore: false, loaded: false });
  const debounce = typeof field.debounce === 'number' ? field.debounce : 500;

  const uriKey = (shared.resource as { uriKey?: string } | undefined)?.uriKey ?? '';
  const attribute = typeof field.relationship === 'string' ? field.relationship : field.attribute;
  const endpoint =
    shared.mode === 'update'
      ? `${apiPath}/resources/${uriKey}/${String(shared.id)}/${attribute}/associatable`
      : `${apiPath}/resources/${uriKey}/associatable/${attribute}`;

  useEffect(() => {
    const timer = setTimeout(() => {
      setQuery(term.trim());
    }, debounce);

    return () => {
      clearTimeout(timer);
    };
  }, [term, debounce]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const controller = new AbortController();
    const params = new URLSearchParams({ q: query, page: String(page) });

    if (withTrashed) {
      params.set('with_trashed', '1');
    }

    request<AssociatablePage>(`${endpoint}?${params.toString()}`, { signal: controller.signal }).then(
      (reply) => {
        const items = reply?.items ?? [];
        setResult((current) => ({ items: page === 1 ? items : [...current.items, ...items], hasMore: reply?.has_more === true, loaded: true }));
      },
      () => {
        // Typing on or closing aborts the request it replaces; only a real failure toasts.
        if (!controller.signal.aborted) {
          toast.error(__('Something went wrong. Please try again.'), {
            action: {
              label: __('Retry'),
              onClick: () => {
                setAttempt((count) => count + 1);
              },
            },
          });
        }
      },
    );

    return () => {
      controller.abort();
    };
  }, [open, endpoint, query, page, withTrashed, attempt, __]);

  return {
    ...result,
    more: () => {
      setPaging({ key, page: page + 1 });
    },
  };
}
