// The cell cursor of a list table: one cell holds the tab stop and the keys move it
// over the data rows, never a group header or a summary row. The cursor is the focused cell, so it
// lives in the DOM, not in React state, and a render that keeps the rows leaves it where it was.
import { showKeys } from '../lib/keys';

/** PageUp and PageDown move this many rows. */
const pageRows = 25;

// Focus here means the person types, so the keys belong to the control.
const typing = 'input:not([type=checkbox]):not([type=radio]), textarea, select, [contenteditable], [role=combobox]';

export interface Grid {
  /** Hands the tab stop back to the cursor's cell after a render; a new first cell may have taken it. */
  sync: () => void;
  detach: () => void;
}

/** The row selection, by the place of a row among the data rows. */
export interface GridSelection {
  toggle: (row: number) => void;
  /** Selects from the anchor, or from `from` when none was toggled, to `to`. */
  extend: (from: number, to: number) => void;
  clear: () => void;
}

// The selection is React state, so the table hands in a ref it refreshes on each render.
export function attachGrid(table: HTMLTableElement, select: { current: GridSelection }): Grid {
  let row = 0;
  let column = -1;

  const rows = () => Array.from(table.querySelectorAll<HTMLTableRowElement>('tbody > tr[data-row]'));

  // The data cell holding an element, with its place.
  function locate(target: EventTarget | null): { cell: HTMLTableCellElement; row: number; column: number } | null {
    // A dialog a cell opens, such as the conflict dialog, keeps its keys and clicks to itself.
    const cell = target instanceof Element && target.closest('[role=dialog], [role=alertdialog]') === null ? target.closest('td') : null;
    const index = cell === null || !(cell.parentElement instanceof HTMLTableRowElement) ? -1 : rows().indexOf(cell.parentElement);

    return cell === null || index === -1 ? null : { cell, row: index, column: cell.cellIndex };
  }

  function current(): HTMLTableCellElement | undefined {
    const all = rows();
    const at = all[Math.min(row, all.length - 1)];

    return at?.cells[Math.min(column, at.cells.length - 1)];
  }

  function sync() {
    // A cell's own links and buttons, such as a related record's name or a header's sort, wait for the cursor too.
    // A group header keeps its fold button in the tab order, since the cursor passes over that row.
    for (const control of table.querySelectorAll<HTMLElement>(
      'thead th:not([tabindex]), thead button:not([tabindex]), thead input:not([tabindex]), tr[data-row] a[href]:not([tabindex]), tr[data-row] button:not([tabindex]), tr[data-row] input:not([tabindex])',
    )) {
      control.tabIndex = -1;
    }

    // Before the first move the cell the page rendered with tabindex 0 is the cursor.
    const cell = column === -1 ? undefined : current();

    if (cell === undefined) {
      return;
    }

    for (const other of table.querySelectorAll<HTMLElement>('tbody td[tabindex="0"]')) {
      if (other !== cell) {
        other.tabIndex = -1;
      }
    }

    cell.tabIndex = 0;
  }

  function moveTo(nextRow: number, nextColumn: number) {
    const all = rows();
    row = Math.max(0, Math.min(nextRow, all.length - 1));
    const cells = all[row]?.cells.length ?? 1;
    column = Math.max(0, Math.min(nextColumn, cells - 1));
    sync();
    current()?.focus();
  }

  function onFocusIn(event: FocusEvent) {
    const found = locate(event.target);

    if (found !== null) {
      row = found.row;
      column = found.column;
      sync();
    }
  }

  // An editable cell opens its control on Enter, F2 or a double-click; it answers false otherwise.
  function edit(cell: HTMLTableCellElement): boolean {
    const editable = cell.querySelector('[data-inline-cell]');
    editable?.dispatchEvent(new Event('lathe-edit'));

    return editable !== null;
  }

  function onDoubleClick(event: MouseEvent) {
    const found = locate(event.target);

    if (found !== null) {
      edit(found.cell);
    }
  }

  // Whether the pressed cell held the cursor before the press focused it.
  let held = false;

  function onPointerDown(event: PointerEvent) {
    const found = locate(event.target);
    held = found !== null && found.cell === current() && found.cell.contains(document.activeElement);
  }

  // A click on a cell, not on a control in it, puts the cursor there. A link in a cell, such as a
  // related record's name, moves the cursor first and opens once its cell holds the cursor.
  function onClick(event: MouseEvent) {
    const found = locate(event.target);
    const control = event.target instanceof Element ? event.target.closest('a, button, input, label, select') : null;

    if (found !== null && control === null) {
      found.cell.focus();
    } else if (found !== null && control instanceof HTMLAnchorElement && !held && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
      event.preventDefault();
      found.cell.focus();
    }

    held = false;
  }

  // The header row: the arrows move along it and Down goes back to the rows; Enter presses its sort,
  // its select-all or its Columns button.
  function onHeaderKey(event: KeyboardEvent, header: HTMLTableCellElement) {
    const cells = (header.parentElement as HTMLTableRowElement).cells;
    const step = document.dir === 'rtl' ? -1 : 1;
    const to = { ArrowRight: header.cellIndex + step, ArrowLeft: header.cellIndex - step, Home: 0, End: cells.length - 1 }[event.key];

    if (event.key === 'ArrowDown') {
      event.preventDefault();
      moveTo(0, header.cellIndex);
    } else if (to !== undefined) {
      event.preventDefault();
      cells[Math.max(0, Math.min(to, cells.length - 1))]?.focus();
    } else if (event.key === 'Enter' && event.target === header) {
      event.preventDefault();
      header.querySelector<HTMLElement>('button, input')?.click();
    }
  }

  function onKeyDown(event: KeyboardEvent) {
    const found = locate(event.target);
    const header = event.target instanceof Element ? event.target.closest('thead th') : null;

    if (header instanceof HTMLTableCellElement && !event.altKey && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
      onHeaderKey(event, header);

      return;
    }

    if (found === null || (event.target instanceof Element && event.target.closest(typing) !== null) || event.altKey || event.metaKey || event.ctrlKey) {
      return;
    }

    // In a row's action cell the arrows step between its buttons, Escape goes back to the cell, and
    // past either end the cursor moves on.
    const actions = found.cell.matches('[data-row-menu]') && event.target !== found.cell && event.target instanceof HTMLElement && event.target.closest('[role=menu]') === null;
    const controls = actions ? Array.from(found.cell.querySelectorAll<HTMLElement>('a[href], button')) : [];
    const step = { ArrowRight: document.dir === 'rtl' ? -1 : 1, ArrowLeft: document.dir === 'rtl' ? 1 : -1 }[event.key];
    const sibling = step === undefined ? undefined : controls[controls.indexOf(event.target as HTMLElement) + step];

    if (sibling !== undefined || (actions && event.key === 'Escape')) {
      event.preventDefault();
      (sibling ?? found.cell).focus();

      return;
    }

    const moves: Record<string, [number, number]> = {
      ArrowDown: [found.row + 1, found.column],
      ArrowUp: [found.row - 1, found.column],
      ArrowRight: [found.row, found.column + (document.dir === 'rtl' ? -1 : 1)],
      ArrowLeft: [found.row, found.column + (document.dir === 'rtl' ? 1 : -1)],
      Home: [found.row, 0],
      End: [found.row, Number.MAX_SAFE_INTEGER],
      PageDown: [found.row + pageRows, found.column],
      PageUp: [found.row - pageRows, found.column],
    };
    // Shift+Up and Shift+Down move and select; Shift with any other move is not ours.
    const move = event.shiftKey && event.key !== 'ArrowDown' && event.key !== 'ArrowUp' ? undefined : moves[event.key];

    if (event.key === 'ArrowUp' && !event.shiftKey && found.row === 0) {
      event.preventDefault();
      table.tHead?.rows[0]?.cells[found.column]?.focus();
    } else if (move !== undefined) {
      event.preventDefault();
      moveTo(...move);

      if (event.shiftKey) {
        select.current.extend(found.row, row);
      }
    } else if (event.key === 'x' || event.key === 'X') {
      event.preventDefault();
      select.current.toggle(found.row);
    } else if (event.key === 'Escape' && (event.target === found.cell || event.target instanceof HTMLInputElement)) {
      // From the cell or its checkbox only: a row menu closes on its own Escape.
      select.current.clear();
    } else if (event.key === '?') {
      event.preventDefault();
      showKeys();
    } else if ((event.key === 'Enter' || event.key === 'F2') && event.target === found.cell && edit(found.cell)) {
      event.preventDefault();
    } else if (event.key === 'Enter' && event.target === found.cell && found.cell.matches('[data-row-menu]')) {
      // A row's action cell hands the focus to its first button.
      event.preventDefault();
      found.cell.querySelector<HTMLElement>('a[href], button')?.focus();
    } else if (event.key === 'Enter' && event.target === found.cell) {
      // The checkbox cell ticks; any other runs the row's click action through its title cell.
      const control = found.cell.querySelector<HTMLElement>('input[type=checkbox]') ?? found.cell.parentElement?.querySelector<HTMLElement>('td[data-open]');

      if (control !== null && control !== undefined) {
        event.preventDefault();
        control.click();
      }
    }
  }

  table.addEventListener('focusin', onFocusIn);
  table.addEventListener('pointerdown', onPointerDown);
  table.addEventListener('click', onClick);
  table.addEventListener('keydown', onKeyDown);
  table.addEventListener('dblclick', onDoubleClick);
  table.dataset.gridReady = '';
  sync();

  return {
    sync,
    detach() {
      table.removeEventListener('focusin', onFocusIn);
      table.removeEventListener('pointerdown', onPointerDown);
      table.removeEventListener('click', onClick);
      table.removeEventListener('keydown', onKeyDown);
      table.removeEventListener('dblclick', onDoubleClick);
      delete table.dataset.gridReady;
    },
  };
}
