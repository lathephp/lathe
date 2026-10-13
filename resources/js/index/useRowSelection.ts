import { useState } from 'react';
import type { Row } from './types';

export type RowSelection = ReturnType<typeof useRowSelection>;

/** The ticked rows by id. It lives on the page, so a polling reload of the table keeps it. */
export function useRowSelection() {
  const [selected, setSelected] = useState<ReadonlySet<Row['id']>>(new Set());

  function toggle(id: Row['id']) {
    const next = new Set(selected);

    if (!next.delete(id)) {
      next.add(id);
    }

    setSelected(next);
  }

  /** Ticks every row given, or unticks them all when every one is already ticked. */
  function toggleAll(ids: Row['id'][]) {
    const next = new Set(selected);
    const all = ids.every((id) => selected.has(id));

    for (const id of ids) {
      if (all) {
        next.delete(id);
      } else {
        next.add(id);
      }
    }

    setSelected(next);
  }

  return { selected, toggle, toggleAll };
}
