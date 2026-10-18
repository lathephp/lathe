import { Link, usePage } from '@inertiajs/react';
import { TableCell } from '../table/cells';
import type { SharedProps } from '../types';
import type { IndexProps, Row } from './types';

/**
 * Below 768 px each row is a card: its title, then its badge and value, then its relation, the
 * columns the server picked. A column the user hid still shows its text, since its cell is sent.
 */
export function PhoneCards({ resource, table }: Pick<IndexProps, 'resource' | 'table'>) {
  const { basePath } = usePage<SharedProps>().props;
  const card = table.phoneCard;

  if (card === undefined) {
    return null;
  }

  function part(row: Row, key: string | null) {
    const cell = key === null ? undefined : row.cells[key];
    const column = table.columns.find((candidate) => candidate.key === key);

    return cell === undefined ? null : column === undefined ? cell.display : <TableCell column={column} cell={cell} />;
  }

  return (
    <ul data-lathe-cards="" className="space-y-2 p-3 md:hidden">
      {table.rows.map((row) => (
        <li key={row.id}>
          <Link href={`${basePath}/resources/${resource.uriKey}/${String(row.id)}`} className="block space-y-1 rounded-lg border border-border p-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <span className="flex items-center justify-between gap-2">
              <span className="truncate font-medium">{card.title === null ? row.title : part(row, card.title)}</span>
              {part(row, card.badge)}
            </span>
            <span className="flex items-center justify-between gap-2 text-muted-foreground">
              <span className="truncate">{part(row, card.meta)}</span>
              <span className="text-foreground tabular-nums">{part(row, card.value)}</span>
            </span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
