import { request, useLathe } from '@lathephp/core';
import { useEffect, useEffectEvent } from 'react';
import type { Row } from '../index/types';
import { recordSelection } from './selection';
import type { ActionList, ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';
import { reloadIndexRows } from '../lib/instant';

/** The row's inline actions, with what runs one; the row menu draws them as its items. */
export interface InlineActions {
  actions: ActionMeta[];
  run: (action: ActionMeta) => void;
}

interface InlineRowActionsProps {
  uriKey: string;
  row: Row;
  /** Called with the actions once they load, so the row menu can list them. */
  onLoad: (inline: InlineActions) => void;
}

/**
 * The actions shown on rows, as items of a row's menu. They load the first time the menu
 * opens; the component stays mounted beside the menu after, so a modal it opened outlives
 * the menu.
 */
export function InlineRowActions({ uriKey, row, onLoad }: InlineRowActionsProps) {
  const { apiPath } = useLathe();
  const runner = useActionRunner({
    base: `${apiPath}/resources/${uriKey}`,
    onDone: () => {
      reloadIndexRows();
    },
  });
  const start = useEffectEvent((action: ActionMeta) => {
    runner.start(action, recordSelection(row.id), 1);
  });
  const loaded = useEffectEvent((actions: ActionMeta[]) => {
    onLoad({
      actions: actions.filter((action) => row.actions?.includes(action.uriKey) === true),
      run: (action) => {
        start(action);
      },
    });
  });

  useEffect(() => {
    let live = true;

    request<ActionList>(`${apiPath}/resources/${uriKey}/actions?display=table-row`)
      .then((list) => {
        if (live) {
          loaded(list?.actions ?? []);
        }
      })
      .catch(() => undefined);

    return () => {
      live = false;
    };
  }, [apiPath, uriKey]);

  return runner.element;
}
