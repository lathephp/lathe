import { useTranslation } from '@lathephp/core';
import { Ellipsis } from 'lucide-react';
import { Suspense, useState } from 'react';
import { RowMenuPanel } from './lazy';
import type { Row } from './types';
import { buttonClass } from '../components/ui/Button';

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

/** The row's menu button; the menu's code loads on the first open, and pointing at the button or pressing it starts it early. */
export function RowMenu({ uriKey, row }: { uriKey: string; row: Row }) {
  const { __ } = useTranslation();
  // Set on the first open, to how it opened; the kit menu then takes this button's place and stays, so the row's actions keep their state.
  const [opened, setOpened] = useState<'keyboard' | 'pointer' | null>(null);
  const label = __('Actions for :title', { title: row.title });
  const preload = () => {
    void RowMenuPanel.preload();
  };
  const button = (
    <button
      type="button"
      tabIndex={-1}
      aria-haspopup="menu"
      aria-expanded="false"
      aria-label={label}
      className={buttonClass('ghost', 'icon-sm')}
      onPointerEnter={preload}
      onPointerDown={preload}
      // A click from Enter or Space has no presses to count.
      onClick={(event) => {
        setOpened(event.detail === 0 ? 'keyboard' : 'pointer');
      }}
    >
      <Ellipsis aria-hidden="true" className="size-4" />
    </button>
  );

  return (
    <div className="relative flex justify-end">
      {opened !== null ? (
        <Suspense fallback={button}>
          <RowMenuPanel uriKey={uriKey} row={row} label={label} fromKeyboard={opened === 'keyboard'} />
        </Suspense>
      ) : (
        button
      )}
    </div>
  );
}
