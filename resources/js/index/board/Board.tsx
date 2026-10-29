import { useTranslation } from '@lathephp/core';
import type { KeyboardEvent } from 'react';
import { SelectBadge, styled } from '../../fields/choice/SelectFields';
import { TableCell } from '../../table/cells';
import { countText } from '../countText';
import type { IndexProps, Row } from '../types';
import { MoveMenu } from './MoveMenu';
import { useBoardColumns } from './useBoardColumns';
import { canMove, useCardMoves } from './useCardMoves';

type BoardProps = Pick<IndexProps, 'resource' | 'table'> & {
  board: NonNullable<IndexProps['table']['board']>;
  /** The preview panel: a click opens it on a card, Space toggles it, and an open panel follows the focus. */
  preview: { show: (id: Row['id']) => void; toggle: (id: Row['id']) => void; follow: (id: Row['id']) => void };
};

// The arrows step to the next card in a column, or across to the card at the same height in the next one.
const steps: Record<string, [number, number]> = { ArrowUp: [0, -1], ArrowDown: [0, 1], ArrowLeft: [-1, 0], ArrowRight: [1, 0] };

/** The card an arrow key reaches from this one, or null at the Board's edge. */
function reached(from: HTMLElement, key: string): HTMLElement | null {
  const [across, down] = steps[key] ?? [0, 0];
  const sections = Array.from(from.closest('[data-lathe-board]')?.querySelectorAll('section') ?? []);
  const at = sections.findIndex((section) => section.contains(from));
  const cardsOf = (section: Element | undefined) => Array.from(section?.querySelectorAll<HTMLElement>('[data-board-card]') ?? []);
  const row = cardsOf(sections[at]).indexOf(from);
  const target = cardsOf(sections[at + across]);

  return across === 0 ? (target[row + down] ?? null) : (target[Math.min(row, target.length - 1)] ?? null);
}

/**
 * The list as a Board: a column per option of the board field, each headed by the option and its
 * count, holding the records as the cards phoneCard() picks, 25 at a time.
 */
export function Board({ resource, table, board, preview }: BoardProps) {
  const { __ } = useTranslation();
  const state = useBoardColumns(resource.uriKey, board);
  const { columns, showMore } = state;
  const moves = useCardMoves(resource.uriKey, board, state);
  const card = table.phoneCard;

  function part(row: Row, key: string | null | undefined) {
    const cell = key == null ? undefined : row.cells[key];
    const column = table.columns.find((candidate) => candidate.key === key);

    return cell === undefined ? null : column === undefined ? cell.display : <TableCell column={column} cell={cell} />;
  }

  // Keys a card does not take itself; Shift with an arrow moves the card, so it is left alone here.
  function onKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    const from = event.target instanceof HTMLElement && event.target.matches('[data-board-card]') ? event.target : null;
    const id = from?.dataset.boardCard;

    if (from === null || id === undefined || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey) {
      return;
    }

    if (event.key === ' ') {
      event.preventDefault();
      preview.toggle(id);
    } else if (event.key in steps) {
      event.preventDefault();
      const next = reached(from, event.key);

      if (next?.dataset.boardCard !== undefined) {
        next.focus();
        preview.follow(next.dataset.boardCard);
      }
    }
  }

  return (
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions -- the cards inside take the keys; this listens for them all
    <div data-lathe-board="" onKeyDown={onKeyDown} className="flex gap-2 overflow-x-auto p-3">
      {columns.map((column) => {
        const label = column.value === null ? __('No value') : column.label;

        return (
          <section
            key={column.value ?? ''}
            ref={moves.dropTarget(column.value)}
            aria-label={label}
            className={`flex max-w-80 min-w-0 flex-[1_0_196px] flex-col rounded-lg bg-muted shadow-[inset_0_0_0_1px_var(--border)] ${moves.isOver(column.value) ? 'ring-2 ring-ring' : ''}`}
          >
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
                    {/* A focus stop, as a grid cell is; the Board's own listener takes its arrows and Space. */}
                    {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions -- Space opens it from the Board's listener */}
                    <div
                      // eslint-disable-next-line jsx-a11y/no-noninteractive-tabindex
                      tabIndex={0}
                      data-board-card={row.id}
                      onClick={() => {
                        preview.show(row.id);
                      }}
                      {...moves.cardProps(row, column.value)}
                      className="flex flex-col gap-1 rounded-md border border-border bg-card px-[11px] py-[9px] text-sm text-muted-foreground outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      <span className="flex items-center justify-between gap-2">
                        <b className="min-w-0 truncate font-semibold text-foreground">{card?.title == null ? row.title : part(row, card.title)}</b>
                        {canMove(row) && (
                          <MoveMenu
                            title={row.title}
                            columns={columns.filter((each) => each.value !== column.value)}
                            onMove={(to) => {
                              moves.moveTo(row, to);
                            }}
                          />
                        )}
                      </span>
                      {card?.meta != null && <span className="truncate">{row.cells[card.meta]?.display}</span>}
                      <span className="flex items-center justify-between gap-2">
                        {part(row, card?.badge)}
                        <span className="ms-auto font-medium text-foreground tabular-nums">{part(row, card?.value)}</span>
                      </span>
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
