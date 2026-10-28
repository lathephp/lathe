import { useTranslation } from '@lathephp/core';
import { SelectBadge, styled } from '../../fields/choice/SelectFields';
import { TableCell } from '../../table/cells';
import { countText } from '../IndexFooter';
import type { IndexProps, Row } from '../types';
import { useBoardColumns } from './useBoardColumns';

type BoardProps = Pick<IndexProps, 'resource' | 'table'> & { board: NonNullable<IndexProps['table']['board']> };

/**
 * The list as a Board: a column per option of the board field, each headed by the option and its
 * count, holding the records as the cards phoneCard() picks, 25 at a time.
 */
export function Board({ resource, table, board }: BoardProps) {
  const { __ } = useTranslation();
  const { columns, showMore } = useBoardColumns(resource.uriKey, board);
  const card = table.phoneCard;

  function part(row: Row, key: string | null | undefined) {
    const cell = key == null ? undefined : row.cells[key];
    const column = table.columns.find((candidate) => candidate.key === key);

    return cell === undefined ? null : column === undefined ? cell.display : <TableCell column={column} cell={cell} />;
  }

  return (
    <div data-lathe-board="" className="flex gap-2 overflow-x-auto p-3">
      {columns.map((column) => {
        const label = column.value === null ? __('No value') : column.label;

        return (
          <section key={column.value ?? ''} aria-label={label} className="flex max-w-80 min-w-0 flex-[1_0_196px] flex-col rounded-lg bg-muted shadow-[inset_0_0_0_1px_var(--border)]">
            <header className="flex items-center justify-between gap-2 px-3 pt-2.5 pb-2 text-sm font-medium">
              {column.value !== null && styled(board.field) ? <SelectBadge field={board.field} value={column.value} /> : <span>{label}</span>}
              {column.count !== undefined && <span className="text-xs text-muted-foreground tabular-nums">{countText(column.count)}</span>}
            </header>
            {!column.loading && column.rows.length === 0 ? (
              <p className="px-3 pb-3 text-sm text-muted-foreground">{__('No :resource', { resource: resource.label.toLowerCase() })}</p>
            ) : (
              <ul className="flex flex-col gap-1.5 px-2 pb-2">
                {column.rows.map((row) => (
                  <li key={row.id}>
                    {/* A focus stop, as a grid cell is, so the keyboard can reach a card; moving and opening it come with their own handlers. */}
                    <div
                      // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex
                      tabIndex={0}
                      data-board-card={row.id}
                      className="flex flex-col gap-1 rounded-md border border-border bg-card px-[11px] py-[9px] text-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span className="flex items-center justify-between gap-2">
                        <b className="truncate font-semibold text-foreground">{card?.title == null ? row.title : part(row, card.title)}</b>
                        {part(row, card?.badge)}
                      </span>
                      {card?.meta != null && <span className="truncate">{row.cells[card.meta]?.display}</span>}
                      <span className="font-medium text-foreground tabular-nums">{part(row, card?.value)}</span>
                    </div>
                  </li>
                ))}
              </ul>
            )}
            {column.hasMore && (
              <button
                type="button"
                disabled={column.loading}
                onClick={() => {
                  showMore(column.value);
                }}
                className="mx-2 mb-2 h-[30px] rounded-md text-[13px] font-medium text-muted-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
              >
                {__('Show more')}
              </button>
            )}
          </section>
        );
      })}
    </div>
  );
}
