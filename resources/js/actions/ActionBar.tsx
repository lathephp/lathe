import { formatNumber, useLathe, useTranslation } from '@lathephp/core';
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { Fragment } from 'react';
import { NavIcon } from '../components/NavIcon';
import type { Count, Row } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
import type { RowSelection } from '../index/useRowSelection';
import type { ActionList, ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';
import { reloadIndexRows } from '../lib/instant';
interface ActionBarProps {
  uriKey: string;
  /** The lens the index shows, whose routes the run goes through. */
  lens: string | null;
  list: ActionList;
  selection: RowSelection;
  /** The rows of the page, for their run flags. */
  rows: Row[];
  /** How many rows a mode all selection holds, when the count is known. */
  total: Count | null;
  state: IndexUrlState;
}

/**
 * The pinned action buttons and the Actions menu of an index: standalone actions first,
 * then the rest by group, destructive ones last below a separator.
 */
export function ActionBar({ uriKey, lens, list, selection, rows, total, state }: ActionBarProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const base = lens === null ? `${apiPath}/resources/${uriKey}` : `${apiPath}/resources/${uriKey}/lenses/${lens}`;
  const ticked = selection.all ? (total?.value ?? Number.POSITIVE_INFINITY) : selection.selected.size;
  const tickedRows = rows.filter((row) => selection.isSelected(row.id));
  const runner = useActionRunner({
    base,
    onDone: (action) => {
      if (!action.standalone) {
        selection.clear();
      }

      reloadIndexRows();
    },
  });

  // With nothing ticked only a standalone action can run, so the rest stay out of the menu.
  const shown = ticked > 0 ? list.actions : list.actions.filter((action) => action.standalone);

  // The runner stays, since a run that cleared the ticked rows may still show its response.
  if (shown.length === 0) {
    return runner.element;
  }

  function enabled(action: ActionMeta): boolean {
    if (action.authorizedToRun === false) {
      return false;
    }

    if (action.standalone) {
      return true;
    }

    if (ticked === 0 || (action.sole && ticked !== 1)) {
      return false;
    }

    // A row flag the server sent says whether the user may run the action on that row.
    return tickedRows.every((row) => row.actions === undefined || row.actions.includes(action.uriKey));
  }

  function choose(action: ActionMeta) {
    // A capped count confirms as 10,000+, as the footer shows it. Importing the footer's countText
    // here would split it out of the page's chunk and cost the first load a file.
    runner.start(action, selection.descriptor(state, lens), Number.isFinite(ticked) ? ticked : 0, selection.all && total?.capped === true ? `${formatNumber(total.value)}+` : undefined);
  }

  const pinned = ticked > 0 ? list.actions.filter((action) => action.pinned) : [];
  const destructiveFrom = shown.findIndex((action) => action.destructive && !action.standalone);
  // An item with no icon keeps an icon's width when others have one, so the labels line up.
  const iconGap = shown.some((action) => action.icon !== null);
  // A group heading sits over each run of same-group actions; standalone and destructive ones take none.
  const headingOf = (action: ActionMeta) => (!action.standalone && !action.destructive ? action.group : null);
  const runs = shown.reduce<ActionMeta[][]>((acc, action) => {
    const last = acc.at(-1);
    const group = headingOf(action);

    if (last?.[0] !== undefined && group !== null && headingOf(last[0]) === group) {
      last.push(action);
    } else {
      acc.push([action]);
    }

    return acc;
  }, []);

  return (
    <div className="flex flex-wrap items-center gap-2">
      {pinned.map((action) => (
        <Button
          key={action.uriKey}
          variant="outline"
          size="sm"
          disabled={!enabled(action)}
          onClick={() => {
            choose(action);
          }}
        >
          {action.name}
        </Button>
      ))}
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
          {__('Actions')}
          <ChevronDown aria-hidden="true" className="size-4" />
        </DropdownMenuTrigger>
        {/* On the body, above the sidebar; it ends at the button's end edge and moves in to stay 8 px inside the window. */}
        <DropdownMenuContent align="end" collisionPadding={8} aria-label={__('Actions')} className="w-72">
          {runs.map((run) => {
            const heading = headingOf(run[0] as ActionMeta);
            const items = run.map((action) => (
              <DropdownMenuItem
                key={action.uriKey}
                variant={action.destructive ? 'destructive' : 'default'}
                disabled={!enabled(action)}
                className="items-start gap-2"
                onClick={() => {
                  choose(action);
                }}
              >
                {action.icon !== null ? <NavIcon name={action.icon} /> : iconGap && <span aria-hidden="true" className="size-4 shrink-0" />}
                <span className="min-w-0">
                  <span className="block">{action.name}</span>
                  {action.description !== null && <span className="block text-xs text-muted-foreground">{action.description}</span>}
                </span>
              </DropdownMenuItem>
            ));

            return (
              <Fragment key={run[0]?.uriKey}>
                {run[0] === shown[destructiveFrom] && destructiveFrom > 0 && <DropdownMenuSeparator />}
                {heading === null ? (
                  items
                ) : (
                  <DropdownMenuGroup>
                    <DropdownMenuLabel>{heading}</DropdownMenuLabel>
                    {items}
                  </DropdownMenuGroup>
                )}
              </Fragment>
            );
          })}
        </DropdownMenuContent>
      </DropdownMenu>
      {runner.element}
    </div>
  );
}
