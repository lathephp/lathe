import { createElement, type ComponentType } from 'react';

export interface Cell {
  value: string | number | boolean | null;
  display: string;
}

export interface CellProps {
  cell: Cell;
}

/** The display string the server formatted, as text: a cell never injects HTML. */
function TextCell({ cell }: CellProps) {
  return cell.display;
}

/** The index cell registry: a column's component key to its renderer. */
const cells: Record<string, ComponentType<CellProps> | undefined> = { text: TextCell };

/** Render a cell through the registry. An unknown key renders the display string. */
export function TableCell({ component, cell }: { component: string; cell: Cell }) {
  return createElement(cells[component] ?? TextCell, { cell });
}
