import { useState } from 'react';
import type { Row } from './types';
import type { FilterValue, IndexUrlState } from './useIndexState';

export type RowSelection = ReturnType<typeof useRowSelection>;

/** The selection body an action or a batch delete sends; the server resolves it through the index query. */
export interface SelectionDescriptor {
  mode: 'ids' | 'all';
  ids: Row['id'][];
  exclusions: Row['id'][];
  q: string;
  filters: Record<string, FilterValue>;
  tab: string | null;
  saved_tab: string | null;
  trashed: string | null;
  lens: string | null;
  via: string | null;
}

/**
 * The ticked rows by id, or every matching row but the unticked ones. It lives on the
 * page, so a polling reload of the table keeps it.
 */
export function useRowSelection() {
  const [selected, setSelected] = useState<ReadonlySet<Row['id']>>(new Set());
  // Set while every matching row is selected: the rows unticked since.
  const [exclusions, setExclusions] = useState<ReadonlySet<Row['id']> | null>(null);
  const all = exclusions !== null;

  function isSelected(id: Row['id']): boolean {
    return exclusions !== null ? !exclusions.has(id) : selected.has(id);
  }

  function toggle(id: Row['id']) {
    const next = new Set(exclusions ?? selected);

    if (!next.delete(id)) {
      next.add(id);
    }

    if (exclusions !== null) {
      setExclusions(next);
    } else {
      setSelected(next);
    }
  }

  /** Ticks every row given, or unticks them all when every one is already ticked. */
  function toggleAll(ids: Row['id'][]) {
    if (exclusions !== null) {
      clear();

      return;
    }

    const next = new Set(selected);
    const every = ids.every((id) => selected.has(id));

    for (const id of ids) {
      if (every) {
        next.delete(id);
      } else {
        next.add(id);
      }
    }

    setSelected(next);
  }

  /** Selects every row the index state matches, on every page. */
  function selectAllMatching() {
    setExclusions(new Set());
  }

  function clear() {
    setSelected(new Set());
    setExclusions(null);
  }

  /** The body for the server: the ticked ids, or the index state and the unticked rows. */
  function descriptor(state: IndexUrlState, lens: string | null = null, via: string | null = null): SelectionDescriptor {
    return {
      mode: all ? 'all' : 'ids',
      ids: all ? [] : [...selected],
      exclusions: exclusions === null ? [] : [...exclusions],
      q: state.q ?? '',
      filters: state.filter ?? {},
      tab: state.tab ?? null,
      saved_tab: state.saved_tab ?? null,
      trashed: state.trashed ?? null,
      lens,
      via,
    };
  }

  return { selected, all, exclusions, isSelected, toggle, toggleAll, selectAllMatching, clear, descriptor };
}
