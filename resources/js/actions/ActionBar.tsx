import { router, usePage } from '@inertiajs/react';
import { ApiError, toast, useLathe, useTranslation, visit } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { Fragment, useCallback, useId, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import type { Row } from '../index/types';
import type { IndexUrlState } from '../index/useIndexState';
import type { RowSelection } from '../index/useRowSelection';
import { cn } from '../lib/cn';
import { ReauthModal } from '../records/ReauthModal';
import type { SharedProps } from '../types';
import { ActionModal } from './ActionModal';
import { ResponseModal } from './ResponseModal';
import { applyActionResult } from './result';
import { runAction } from './runAction';
import type { ActionList, ActionMeta } from './types';

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
  const { auth } = usePage<SharedProps>().props;
  const menuId = useId();
  const [open, setOpen] = useState(false);
  const [running, setRunning] = useState<ActionMeta | null>(null);
  const [response, setResponse] = useState<{ component: string; data: Record<string, unknown> } | null>(null);
  const [reauth, setReauth] = useState<(() => void) | null>(null);
  const base = lens === null ? `${apiPath}/resources/${uriKey}` : `${apiPath}/resources/${uriKey}/lenses/${lens}`;
  const ticked = selection.all ? (total ?? Number.POSITIVE_INFINITY) : selection.selected.size;
  const tickedRows = rows.filter((row) => selection.isSelected(row.id));
  const closeRunning = useCallback(() => {
    setRunning(null);
  }, []);
  const closeResponse = useCallback(() => {
    setResponse(null);
  }, []);

  if (list.actions.length === 0) {
    return null;
  }

  function enabled(action: ActionMeta): boolean {
    if (action.standalone) {
      return true;
    }

    if (ticked === 0 || (action.sole && ticked !== 1)) {
      return false;
    }

    // A row flag the server sent says whether the user may run the action on that row.
    return tickedRows.every((row) => row.actions === undefined || row.actions.includes(action.uriKey));
  }

  async function execute(action: ActionMeta, fields: Record<string, unknown>): Promise<void> {
    try {
      const run = await runAction(`${base}/action`, action.uriKey, selection.descriptor(state, lens), fields);
      setRunning(null);
      applyActionResult(run.result, {
        openModal: (component, data) => {
          setResponse({ component, data });
        },
        refresh: () => undefined,
        visit: (path, options) => {
          visit(path, options);
        },
      });

      if (run.bulk_run !== undefined) {
        window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: run.bulk_run }));
      }

      if (!action.standalone) {
        selection.clear();
      }

      if (run.result.effect?.type !== 'redirect' && run.result.effect?.type !== 'visit') {
        router.reload({ only: ['table', 'count'] });
      }
    } catch (error) {
      if (error instanceof ApiError && (error.status === 401 || error.status === 419)) {
        setReauth(() => () => {
          void execute(action, fields);
        });
      } else if (error instanceof ApiError && error.status === 422) {
        const message = firstMessage(error.data);

        // A modal shows the messages under its fields; with none open, the first one toasts.
        if (running === null && message !== null) {
          toast.error(message);
        }

        throw error;
      } else {
        toast.error(__('Something went wrong. Please try again.'), {
          action: {
            label: __('Retry'),
            onClick: () => {
              void execute(action, fields);
            },
          },
        });
      }
    }
  }

  function choose(action: ActionMeta) {
    setOpen(false);

    if (action.withoutConfirmation) {
      void execute(action, {}).catch(() => undefined);
    } else {
      setRunning(action);
    }
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
      {running !== null && (
        <ActionModal
          base={base}
          action={running}
          count={Number.isFinite(ticked) ? ticked : 0}
          onClose={closeRunning}
          onRun={(fields) => execute(running, fields)}
        />
      )}
      {response !== null && <ResponseModal component={response.component} data={response.data} onClose={closeResponse} />}
      {reauth !== null && (
        <ReauthModal
          email={auth.user?.email ?? ''}
          onSignedIn={() => {
            const retry = reauth;
            setReauth(null);
            retry();
          }}
          onOtherUser={() => {
            setReauth(null);
            router.reload();
          }}
          onClose={() => {
            setReauth(null);
          }}
        />
      )}
    </div>
  );
}

function firstMessage(data: unknown): string | null {
  const errors = (data as { errors?: Record<string, string[] | undefined> } | null)?.errors;
  const first = errors === undefined ? undefined : Object.values(errors)[0]?.[0];

  return first ?? null;
}
