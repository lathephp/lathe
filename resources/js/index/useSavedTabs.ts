import { router, usePage } from '@inertiajs/react';
import { ApiError, request, toast, useTranslation } from '@lathephp/core';
import { useState } from 'react';
import type { SharedProps } from '../types';
import type { SavedTab } from './types';
import type { IndexUrlState } from './useIndexState';

/** The body of a new saved tab: the view as the URL holds it. */
export interface NewSavedTab {
  name: string;
  filters: Record<string, unknown>;
  q: string | null;
  sort: string | null;
  trashed: string | null;
  show_count: boolean;
  badge_type: string | null;
}

function reload() {
  router.reload({ only: ['tabs', 'tabCounts'] });
}

/** The first validation message of a 422, else the server's message. */
function messageOf(error: unknown): string | null {
  if (!(error instanceof ApiError) || typeof error.data !== 'object' || error.data === null) {
    return null;
  }

  const data = error.data as { message?: unknown; errors?: Record<string, unknown> };
  const first = Object.values(data.errors ?? {})[0];

  if (Array.isArray(first) && typeof first[0] === 'string') {
    return first[0];
  }

  return typeof data.message === 'string' ? data.message : null;
}

/** The user's saved tabs of one Resource, with the calls that change them. Each change reloads the tabs. */
export function useSavedTabs(uriKey: string, saved: SavedTab[], activeSaved: number | null, visit: (changes: IndexUrlState) => void) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const url = `${basePath}/api/resources/${uriKey}/saved-tabs`;
  const ids = saved.map((tab) => tab.id).join(',');
  const [seen, setSeen] = useState(ids);
  // A move shows at once; the reloaded tabs replace it, or a refused one puts the old order back.
  const [order, setOrder] = useState<number[] | null>(null);

  if (ids !== seen) {
    setSeen(ids);
    setOrder(null);
  }

  const list = order === null ? saved : order.flatMap((id) => saved.filter((tab) => tab.id === id));

  async function send(path: string, method: string, body?: unknown): Promise<boolean> {
    try {
      await request(`${url}${path}`, { method, body, toastOnError: false });

      return true;
    } catch (error) {
      toast.error(messageOf(error) ?? __('The view could not be saved'));

      return false;
    }
  }

  return {
    list,
    async create(tab: NewSavedTab) {
      const done = await send('', 'POST', tab);

      if (done) {
        reload();
      }

      return done;
    },
    async update(id: number, changes: Partial<Pick<NewSavedTab, 'name' | 'filters' | 'sort'>>) {
      const done = await send(`/${String(id)}`, 'PUT', changes);

      if (done) {
        reload();
      }

      return done;
    },
    async delete(id: number) {
      const done = await send(`/${String(id)}`, 'DELETE');

      // The deleted tab's URL now answers 404, so the page leaves it.
      if (done && id === activeSaved) {
        visit({ saved_tab: undefined });
      } else if (done) {
        reload();
      }

      return done;
    },
    async reorder(next: number[]) {
      const previous = order;
      setOrder(next);
      const done = await send('/reorder', 'PUT', { ids: next });

      if (done) {
        reload();
      } else {
        setOrder(previous);
      }

      return done;
    },
  };
}
