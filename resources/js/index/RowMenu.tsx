import { useTranslation } from '@lathephp/core';
import { EllipsisVertical } from 'lucide-react';
import { Suspense, useId, useRef, useState } from 'react';
import { RowMenuPanel } from './lazy';
import type { Row } from './types';

export type MenuAction = 'view' | 'edit' | 'replicate' | 'delete' | 'restore' | 'forceDelete';
export type WriteAction = Exclude<MenuAction, 'view' | 'edit' | 'replicate'>;

/** What the row menu offers: View, Edit, Replicate and Delete on a live row; View, Restore and Force Delete on a trashed one. */
export function menuActions(row: Row): MenuAction[] {
  const actions: MenuAction[] = row.trashed === true ? ['view', 'restore', 'forceDelete'] : ['view', 'edit', 'replicate', 'delete'];

  return actions.filter((action) => row.abilities[action === 'edit' ? 'update' : action] === true);
}

/** Whether a row has a menu: a delete family entry, Impersonate, or an action it may run inline. */
export function hasRowMenu(row: Row): boolean {
  return menuActions(row).length > 0 || row.abilities.impersonate === true || (row.actions?.length ?? 0) > 0;
}

/** The row's menu button; the menu's code loads on the first open, and pointing at the button starts it early. */
export function RowMenu({ uriKey, row }: { uriKey: string; row: Row }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  // Set on the first open; the menu then stays mounted, so the row's actions keep their state.
  const [opened, setOpened] = useState(false);
  const menuId = useId();
  const trigger = useRef<HTMLButtonElement>(null);

  return (
    <div className="relative flex justify-end">
      <button
        ref={trigger}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Actions for :title', { title: row.title })}
        className="grid size-7 place-items-center rounded-md outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
        onPointerEnter={() => {
          void RowMenuPanel.preload();
        }}
        onClick={() => {
          setOpen(!open);
          setOpened(true);
        }}
      >
        <EllipsisVertical aria-hidden="true" className="size-4" />
      </button>
      {opened && (
        <Suspense fallback={null}>
          <RowMenuPanel
            uriKey={uriKey}
            row={row}
            open={open}
            menuId={menuId}
            onClose={() => {
              // A closing menu hands the focus back to its button, also when an entry opens a dialog.
              trigger.current?.focus();
              setOpen(false);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
