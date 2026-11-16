import { useTranslation } from '@lathephp/core';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@lathephp/ui';
import { ArrowRightLeft } from 'lucide-react';
import { Button } from '../../components/ui/Button';

// The keys the Board takes on a card: the arrows move between cards and Space opens the preview.
const boardKeys = new Set(['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' ']);

interface MoveMenuProps {
  title: string;
  /** Every column but the card's own. */
  columns: { value: string | null; label: string }[];
  onMove: (value: string | null) => void;
}

/** A card's Move to menu, the keyboard's way to any column, as the row menu draws its list. */
export function MoveMenu({ title, columns, onMove }: MoveMenuProps) {
  const { __ } = useTranslation();

  return (
    // The card under the menu opens the preview on a click and moves the focus on the arrows, and React sends the
    // portaled menu's events up to it, so neither reaches it from here.
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions
    <span
      onClick={(event) => {
        event.stopPropagation();
      }}
      onKeyDown={(event) => {
        if (boardKeys.has(event.key)) {
          event.stopPropagation();
        }
      }}
    >
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="ghost" size="icon-xs" aria-label={__('Move :title', { title })} />}>
          <ArrowRightLeft aria-hidden="true" className="size-3.5" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" collisionPadding={8} aria-label={__('Move to')} className="w-44">
          <DropdownMenuGroup>
            <DropdownMenuLabel>{__('Move to')}</DropdownMenuLabel>
            {columns.map((column) => (
              <DropdownMenuItem
                key={column.value ?? ''}
                onClick={() => {
                  onMove(column.value);
                }}
              >
                {column.value === null ? __('No value') : column.label}
              </DropdownMenuItem>
            ))}
          </DropdownMenuGroup>
        </DropdownMenuContent>
      </DropdownMenu>
    </span>
  );
}
