import { useLayoutEffect, useState, type RefObject } from 'react';

/** The views rail's 220 px and the 16 px gap between it and the card. */
const RAIL = 220 + 16;

/** Whether the table's columns need more width than the index body leaves beside an open rail. */
export function railFolds(bodyWidth: number, neededWidth: number): boolean {
  return neededWidth > bodyWidth - RAIL;
}

/**
 * The width the table's columns need. The table is w-full, so its own width grows with the card; at
 * max-content it reads the same whether the rail is open or folded, so the fold cannot undo itself.
 */
function intrinsicWidth(table: HTMLElement): number {
  const width = table.style.width;
  table.style.width = 'max-content';
  const needed = table.getBoundingClientRect().width;
  table.style.width = width;

  return needed;
}

/**
 * Whether the views rail folds into the Views menu because the table beside it would not fit. It
 * remeasures when the index body or the table resizes, and when the columns change: hiding a column
 * leaves a stretched table's width as it was, so no observer would see it.
 */
export function useRailFit(body: RefObject<HTMLElement | null>, columns: unknown): boolean {
  const [folded, setFolded] = useState(false);

  // A layout effect, so the first paint already has the rail open or folded.
  useLayoutEffect(() => {
    const element = body.current;

    if (element === null) {
      return;
    }

    let table: HTMLElement | null = null;
    // jsdom has no ResizeObserver.
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(measure);

    function measure() {
      if (element === null) {
        return;
      }

      // The table comes and goes with the rows, the empty state and the Board.
      const found = element.querySelector<HTMLElement>('[data-lathe-table]');

      if (found !== table) {
        if (table !== null) {
          observer?.unobserve(table);
        }

        if (found !== null) {
          observer?.observe(found);
        }

        table = found;
      }

      // A table hidden under 768 px for the phone cards measures 0 and needs no room.
      const needed = found === null ? 0 : intrinsicWidth(found);
      setFolded(needed > 0 && railFolds(element.getBoundingClientRect().width, needed));
    }

    observer?.observe(element);
    measure();

    return () => {
      observer?.disconnect();
    };
  }, [body, columns]);

  return folded;
}
