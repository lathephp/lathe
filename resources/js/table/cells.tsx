import { IndexField } from '../fields/registry';
import type { Cell, FieldSchema } from '../fields/types';

export type { Cell } from '../fields/types';

/** Render a cell through the index registry. An unknown key renders the display string. */
export function TableCell({ component, cell }: { component: string; cell: Cell }) {
  return <IndexField field={{ component } as FieldSchema} cell={cell} />;
}
