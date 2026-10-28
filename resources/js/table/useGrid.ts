import { useEffect, useLayoutEffect, useRef, type RefObject } from 'react';
import type { Row } from '../index/types';
import type { RowSelection } from '../index/useRowSelection';
import type { Grid, GridSelection } from './grid';

/** Only the calls the grid makes, so a plugin can hand in its own selection. */
export type GridRowSelection = Pick<RowSelection, 'isSelected' | 'toggle' | 'toggleAll'> & Partial<Pick<RowSelection, 'clear'>>;

/**
 * The cell cursor of a table and its selection keys, over the data rows in the order they show.
 * Its code loads after the rows paint, so it stays out of the first load; until then the cell the
 * table rendered with tabindex 0 holds the tab stop. Returns the tick a row's checkbox calls,
 * with range for a Shift+click.
 */
export function useGrid(table: RefObject<HTMLTableElement | null>, rows: readonly Pick<Row, 'id'>[], rowSelection: GridRowSelection) {
  const grid = useRef<Grid | null>(null);
  // The row a Shift+click or a Shift+arrow selects from: the last one ticked or toggled with X.
  const anchor = useRef<Row['id'] | undefined>(undefined);

  function tick(id: Row['id'] | undefined, range: boolean) {
    const from = rows.findIndex((row) => row.id === anchor.current);
    const to = rows.findIndex((row) => row.id === id);

    if (range && from !== -1) {
      // Ticks the unticked rows of the range; toggleAll would untick a range already all ticked.
      const ids = rows.slice(Math.min(from, to), Math.max(from, to) + 1).map((row) => row.id).filter((each) => !rowSelection.isSelected(each));

      if (ids.length > 0) {
        rowSelection.toggleAll(ids);
      }
    } else if (id !== undefined) {
      anchor.current = id;
      rowSelection.toggle(id);
    }
  }

  const selection: GridSelection = {
    toggle: (row) => {
      tick(rows[row]?.id, false);
    },
    extend: (from, to) => {
      anchor.current ??= rows[from]?.id;
      tick(rows[to]?.id, true);
    },
    clear: () => {
      anchor.current = undefined;
      rowSelection.clear?.();
    },
  };
  // The grid outlives a render; it reads the selection of the latest one through this ref.
  const select = useRef(selection);

  useEffect(() => {
    let live = true;

    void import('./grid').then(({ attachGrid }) => {
      if (live && table.current !== null) {
        grid.current = attachGrid(table.current, select);
      }
    });

    return () => {
      live = false;
      grid.current?.detach();
      grid.current = null;
    };
  }, [table]);

  useLayoutEffect(() => {
    select.current = selection;
    grid.current?.sync();
  });

  return tick;
}
