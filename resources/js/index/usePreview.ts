import { router, usePage } from '@inertiajs/react';
import { request, useLathe } from '@lathephp/core';
import { useEffect, useRef, useState } from 'react';
import type { Row } from './types';

/** How long the cursor rests on a row before the URL, and the panel's body, follow it. */
export const restMs = 150;

function linked(): string | null {
  return new URLSearchParams(window.location.search).get('preview');
}

/**
 * The record the preview panel shows, or null while it is closed. The URL holds it as
 * ?preview=<id>, replaced and never pushed, so Back leaves the list; whether the panel is open
 * is saved per user per Resource.
 */
export function usePreview(uriKey: string, savedOpen: boolean, first: Row['id'] | undefined) {
  const { apiPath } = useLathe();
  // A sort or a filter visits a URL built from the list's own keys; the panel puts its record back.
  const { url } = usePage();
  // A link names its record; a list the user left with the panel open opens it on the first row.
  const [id, setId] = useState<string | null>(() => linked() ?? (savedOpen && first !== undefined ? String(first) : null));
  const open = useRef(id !== null);

  function save(next: boolean) {
    if (open.current !== next) {
      open.current = next;
      void request(`${apiPath}/resources/${uriKey}/preview-panel`, { method: 'PUT', body: { open: next }, toastOnError: false }).catch(() => undefined);
    }
  }

  // The URL follows once the cursor rests, so holding Down does not flood the history API.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      if (linked() !== id) {
        const next = new URL(window.location.href);

        if (id === null) {
          next.searchParams.delete('preview');
        } else {
          next.searchParams.set('preview', id);
        }

        router.replace({ url: `${next.pathname}${next.search}`, preserveScroll: true, preserveState: true });
      }
    }, restMs);

    return () => {
      window.clearTimeout(timer);
    };
  }, [id, url]);

  return {
    id,
    show: (next: Row['id']) => {
      save(true);
      setId(String(next));
    },
    /** Space: opens on the row, or closes when it already shows that row. */
    toggle: (next: Row['id']) => {
      const same = id === String(next);
      save(!same);
      setId(same ? null : String(next));
    },
    /** The cursor moved: an open panel follows it. */
    follow: (next: Row['id']) => {
      if (open.current) {
        setId(String(next));
      }
    },
    close: () => {
      save(false);
      setId(null);
    },
  };
}
