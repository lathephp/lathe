import { useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { Fragment, useId, useRef, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import type { Row } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
import type { RowSelection } from '../index/useRowSelection';
import { cn } from '../lib/cn';
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
  total: number | null;
  state: IndexUrlState;
}

const itemClass =
  'flex w-full items-start gap-2 rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';

/**
 * The pinned action buttons and the Actions menu of an index: standalone actions first,
 * then the rest by group, destructive ones last below a separator.
 */
export function ActionBar({ uriKey, lens, list, selection, rows, total, state }: ActionBarProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const menuId = useId();
  const [open, setOpen] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const base = lens === null ? `${apiPath}/resources/${uriKey}` : `${apiPath}/resources/${uriKey}/lenses/${lens}`;
  const ticked = selection.all ? (total ?? Number.POSITIVE_INFINITY) : selection.selected.size;
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

  if (list.actions.length === 0) {
    return null;
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
    // From the menu, the focus goes back to Actions, so the dialog the action opens returns it there.
    if (open) {
      trigger.current?.focus();
    }

    setOpen(false);
    runner.start(action, selection.descriptor(state, lens), Number.isFinite(ticked) ? ticked : 0);
  }

  const pinned = ticked > 0 ? list.actions.filter((action) => action.pinned) : [];
  const destructiveFrom = list.actions.findIndex((action) => action.destructive && !action.standalone);

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
      <div className="relative">
        <Button
          ref={trigger}
          variant="outline"
          size="sm"
          aria-haspopup="menu"
          aria-expanded={open}
          aria-controls={open ? menuId : undefined}
          onClick={() => {
            setOpen(!open);
          }}
        >
          {__('Actions')}
          <ChevronDown aria-hidden="true" className="size-4" />
        </Button>
        {open && (
          <div
            id={menuId}
            role="menu"
            aria-label={__('Actions')}
            tabIndex={-1}
            className="absolute end-0 top-full z-20 mt-1 w-72 rounded-lg border border-border bg-background p-1 shadow-lg"
            onKeyDown={(event) => {
              if (event.key === 'Escape') {
                trigger.current?.focus();
                setOpen(false);
              }
            }}
          >
            {list.actions.map((action, index) => {
              const previous = list.actions[index - 1];
              const heading = !action.standalone && !action.destructive && action.group !== null && previous?.group !== action.group ? action.group : null;

              return (
                <Fragment key={action.uriKey}>
                  {index === destructiveFrom && index > 0 && <div role="separator" className="my-1 border-t border-border" />}
                  {heading !== null && (
                    <div role="presentation" className="px-2 pt-2 pb-1 text-xs font-medium text-muted-foreground">
                      {heading}
                    </div>
                  )}
                  <button
                    type="button"
                    role="menuitem"
                    disabled={!enabled(action)}
                    className={cn(itemClass, action.destructive && 'text-destructive')}
                    onClick={() => {
                      choose(action);
                    }}
                  >
                    {action.icon !== null && <NavIcon name={action.icon} />}
                    <span className="min-w-0">
                      <span className="block">{action.name}</span>
                      {action.description !== null && <span className="block text-xs text-muted-foreground">{action.description}</span>}
                    </span>
                  </button>
                </Fragment>
              );
            })}
          </div>
        )}
      </div>
      {runner.element}
    </div>
  );
}
