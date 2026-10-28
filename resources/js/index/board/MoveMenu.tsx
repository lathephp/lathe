import { useTranslation } from '@lathephp/core';
import { ArrowRightLeft } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

// The keys the Board takes on a card: the arrows move between cards and Space opens the preview.
const boardKeys = new Set(['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ']);

const itemClass = 'flex h-8 w-full items-center rounded-md px-2 text-start text-sm text-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring';

interface MoveMenuProps {
  title: string;
  /** Every column but the card's own. */
  columns: { value: string | null; label: string }[];
  onMove: (value: string | null) => void;
}

/** A card's Move to menu, the keyboard's way to any column, as the row menu draws its list. */
export function MoveMenu({ title, columns, onMove }: MoveMenuProps) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const menuId = useId();
  const titleId = useId();
  const trigger = useRef<HTMLButtonElement>(null);
  const panel = useRef<HTMLDivElement>(null);

  // An opening menu takes the focus to its first entry, so Escape and Tab work inside it.
  useEffect(() => {
    if (open) {
      panel.current?.querySelector<HTMLElement>('[role=menuitem]')?.focus();
    }
  }, [open]);

  return (
    // The card under the menu opens the preview on a click and moves the focus on the arrows, so neither reaches it from here.
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions
    <span
      className="relative"
      onClick={(event) => {
        event.stopPropagation();
      }}
      onKeyDown={(event) => {
        if (boardKeys.has(event.key)) {
          event.stopPropagation();
        }
      }}
      onBlur={(event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) {
          setOpen(false);
        }
      }}
    >
      <button
        ref={trigger}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Move :title', { title })}
        className="lathe-row-button size-6"
        onClick={() => {
          setOpen(!open);
        }}
      >
        <ArrowRightLeft aria-hidden="true" className="size-3.5" />
      </button>
      {open && (
        <div
          ref={panel}
          id={menuId}
          role="menu"
          tabIndex={-1}
          aria-labelledby={titleId}
          className="absolute end-0 top-full z-10 mt-1 w-44 rounded-[12px] border border-border bg-popover p-1 text-popover-foreground shadow-md"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              trigger.current?.focus();
              setOpen(false);
            }
          }}
        >
          <div id={titleId} role="presentation" className="px-2 py-1.5 text-xs font-medium text-muted-foreground">
            {__('Move to')}
          </div>
          {columns.map((column) => (
            <button
              key={column.value ?? ''}
              type="button"
              role="menuitem"
              className={itemClass}
              onClick={() => {
                setOpen(false);
                onMove(column.value);
              }}
            >
              {column.value === null ? __('No value') : column.label}
            </button>
          ))}
        </div>
      )}
    </span>
  );
}
