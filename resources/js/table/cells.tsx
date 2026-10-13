import { IndexField } from '../fields/registry';
import type { Cell, FieldSchema } from '../fields/types';

export type { Cell } from '../fields/types';

/**
 * Render a cell through the index registry, with the column's whole schema. A wrapping
 * column wraps onto at most 5 lines; any other shows one line with an ellipsis.
 * An unknown key renders the display string.
 */
export function TableCell({ column, cell }: { column: { component: string; wrapping?: boolean }; cell: Cell }) {
  // The index sends each column as the field's full payload, so the cast only adds what the type left out.
  const field = column as FieldSchema;
  const wrapping = column.wrapping === true;

  // One line needs no element of its own: the cell's td truncates, and a page of 100 rows of
  // 20 columns would mount 2,000 more elements on every render (budget B5).
  if (!wrapping) {
    return <IndexField field={field} cell={cell} />;
  }

  return (
    <div data-wrapping="true" className="line-clamp-5 whitespace-normal">
      <IndexField field={field} cell={cell} />
    </div>
  );
}
