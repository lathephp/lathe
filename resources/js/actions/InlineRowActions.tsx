import { request, useLathe } from '@lathephp/core';
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import type { Row } from '../index/types';
import { recordSelection } from './selection';
import type { ActionList, ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';
import { reloadIndexRows } from '../lib/instant';

interface InlineRowActionsProps {
  uriKey: string;
  row: Row;
  /** The open row menu the items go into; null while it is closed. */
  menu: HTMLElement | null;
  itemClass: string;
  onChoose: () => void;
}

/**
 * The actions shown on rows, as items of a row's menu. They load the first time the menu
 * opens; the component stays mounted beside the menu after, so a modal it opened outlives
 * the menu.
 */
export function InlineRowActions({ uriKey, row, menu, itemClass, onChoose }: InlineRowActionsProps) {
  const { apiPath } = useLathe();
  const [actions, setActions] = useState<ActionMeta[]>([]);
  const runner = useActionRunner({
    base: `${apiPath}/resources/${uriKey}`,
    onDone: () => {
      reloadIndexRows();
    },
  });

  useEffect(() => {
    let live = true;

    request<ActionList>(`${apiPath}/resources/${uriKey}/actions?display=table-row`)
      .then((list) => {
        if (live) {
          setActions(list?.actions ?? []);
        }
      })
      .catch(() => undefined);

    return () => {
      live = false;
    };
  }, [apiPath, uriKey]);

  return (
    <>
      {menu !== null &&
        createPortal(
          actions
            .filter((action) => row.actions?.includes(action.uriKey) === true)
            .map((action) => (
              <button
                key={action.uriKey}
                type="button"
                role="menuitem"
                className={action.destructive ? `${itemClass} text-destructive` : itemClass}
                onClick={() => {
                  onChoose();
                  runner.start(action, recordSelection(row.id), 1);
                }}
              >
                {action.name}
              </button>
            )),
          menu,
        )}
      {runner.element}
    </>
  );
}
