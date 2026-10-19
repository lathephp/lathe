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
    <ul data-lathe-cards="" className="lathe-cards md:hidden">
      {table.rows.map((row) => (
        <li key={row.id}>
          <Link href={`${basePath}/resources/${resource.uriKey}/${String(row.id)}`}>
            <span>
              <b>{card.title === null ? row.title : part(row, card.title)}</b>
              {part(row, card.badge)}
            </span>
            {/* The relation as text: its own link inside the card's would open the related record instead. */}
            {card.meta !== null && <span>{row.cells[card.meta]?.display}</span>}
            <span>{part(row, card.value)}</span>
          </Link>
        </li>
      ))}
    </ul>
  );
}
