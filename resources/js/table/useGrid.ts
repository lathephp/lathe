import { useEffect, useLayoutEffect, useRef, type RefObject } from 'react';
import type { Grid } from './grid';

/**
 * The cell cursor of a table. Its code loads after the rows paint, so it stays out of the first
 * load; until then the cell the table rendered with tabindex 0 holds the tab stop.
 */
export function useGrid(table: RefObject<HTMLTableElement | null>) {
  const grid = useRef<Grid | null>(null);

  useEffect(() => {
    let live = true;

    void import('./grid').then(({ attachGrid }) => {
      if (live && table.current !== null) {
        grid.current = attachGrid(table.current);
      }
    });

    return () => {
      live = false;
      grid.current?.detach();
      grid.current = null;
    };
  }, [table]);

  useLayoutEffect(() => {
    grid.current?.sync();
  });
}
