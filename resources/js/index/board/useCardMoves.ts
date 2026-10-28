import { draggable, dropTargetForElements } from '@atlaskit/pragmatic-drag-and-drop/element/adapter';
import { announce } from '@atlaskit/pragmatic-drag-and-drop-live-region';
import { toast, useLathe, useTranslation } from '@lathephp/core';
import type { KeyboardEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import type { IndexProps, Row } from '../types';
import type { useBoardColumns } from './useBoardColumns';

// The save and the undo stack load on the first move, so their chunks stay out of the list
// Orders' first load carries for the Board. They sit outside the hook because the React
// Compiler skips a function that holds an import().
const saving = () => Promise.all([import('../inlineSave'), import('../undo')]);

type Board = NonNullable<IndexProps['table']['board']>;
type Columns = ReturnType<typeof useBoardColumns>;

/** A card moves only for a user who may update the record and edit it inline, the inline cell's abilities. */
export function canMove(row: Row): boolean {
  return row.abilities.update === true && row.abilities.inlineEdit === true;
}

// One function for every card, so a render does not register the card's drag again; the drop
// reads which card from the element.
function dragCard(element: HTMLElement | null) {
  return element === null ? undefined : draggable({ element, getInitialData: () => ({ card: element.dataset.boardCard }) });
}

/**
 * Moves Board cards between columns by drag, Shift+Left/Right and the Move to menu. A move shows
 * at once, saves the board field through the inline PATCH, and joins the undo stack; a refused
 * move goes back where it was.
 */
export function useCardMoves(uriKey: string, board: Board, columns: Columns) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const [over, setOver] = useState<{ value: string | null } | null>(null);
  // An undo or a drop runs after later renders, so it reads the columns of the latest one.
  const latest = useRef(columns);

  useEffect(() => {
    latest.current = columns;
  });

  function labelOf(value: string | null): string {
    return value === null ? 'No value' : (latest.current.columns.find((column) => column.value === value)?.label ?? '');
  }

  async function move(row: Row, to: string | null, focus: boolean, undoing: boolean): Promise<void> {
    let moved: ReturnType<Columns['move']>;
    flushSync(() => {
      moved = latest.current.move(row.id, to);
    });

    if (moved === undefined) {
      return;
    }

    const from = moved.from;

    // The card now sits in another column, a new element, so the focus follows it there.
    if (focus) {
      document.querySelector<HTMLElement>(`[data-board-card="${CSS.escape(String(row.id))}"]`)?.focus();
    }

    const loading = saving();

    try {
      const [{ patchField }, { lastToken, pushUndo }] = await loading;
      // A move sends the card's token, which a save from this tab has already updated, as a cell
      // does; an undo sends the token this tab's last save returned, as a cell's undo does.
      const token = undoing ? lastToken(`${uriKey}/${String(row.id)}`) : moved.row.updatedAt;
      const reply = await patchField({ apiPath, uriKey, rowId: row.id, key: board.field.key }, to, token);

      if (reply !== null) {
        latest.current.saved(row.id, reply.fields, reply.updatedAt);
      }

      // Announcements stay in English.
      announce(`${row.title} moved to ${labelOf(to)}`);

      if (undoing) {
        toast.success(__('Change undone'));
      } else {
        const take = pushUndo(() => move(row, from, false, true));
        const value = reply?.fields[board.field.key]?.display ?? labelOf(to);

        toast.success(__(':field changed to :value.', { field: board.field.label, value }), { action: { label: __('Undo'), onClick: take } });
      }
    } catch (failure) {
      latest.current.putBack(moved);
      // A save whose code failed to load has no reply to read the reason from.
      toast.error(
        await loading.then(
          ([{ refusalOf }]) => refusalOf(failure, __),
          () => __('Something went wrong. Please try again.'),
        ),
      );
    }
  }

  function step(row: Row, from: string | null, by: number) {
    const at = latest.current.columns.findIndex((column) => column.value === from);
    const target = latest.current.columns[at + by];

    if (target !== undefined) {
      void move(row, target.value, true, false);
    }
  }

  function drop(card: string, to: string | null) {
    const row = latest.current.columns.flatMap((column) => column.rows).find((each) => String(each.id) === card);

    if (row !== undefined) {
      void move(row, to, false, false);
    }
  }

  return {
    /** The card's drag and its Shift+Left/Right; a card the user may not move gets neither. */
    cardProps: (row: Row, column: string | null) =>
      canMove(row)
        ? {
            ref: dragCard,
            onKeyDown: (event: KeyboardEvent<HTMLElement>) => {
              if (!event.shiftKey || event.target !== event.currentTarget || (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight')) {
                return;
              }

              event.preventDefault();
              event.stopPropagation();
              // Right is the next column, or the one before it in a right-to-left page, as the grid's arrows are.
              step(row, column, (event.key === 'ArrowRight') === (document.dir !== 'rtl') ? 1 : -1);
            },
          }
        : {},
    /** Registers a column as a drop target; a card dropped there moves to its value. */
    dropTarget: (value: string | null) => (element: HTMLElement | null) =>
      element === null
        ? undefined
        : dropTargetForElements({
            element,
            canDrop: ({ source }) => typeof source.data.card === 'string',
            onDragEnter: () => {
              setOver({ value });
            },
            onDragLeave: () => {
              setOver(null);
            },
            onDrop: ({ source }) => {
              setOver(null);
              drop(String(source.data.card), value);
            },
          }),
    isOver: (value: string | null) => over !== null && over.value === value,
    /** Moves the card to the column chosen in its Move to menu. */
    moveTo: (row: Row, to: string | null) => {
      void move(row, to, true, false);
    },
  };
}
