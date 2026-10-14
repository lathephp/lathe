import { useLathe, useQueryClient, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { SelectionDescriptor } from '../index/useRowSelection';
import { cn } from '../lib/cn';
import type { ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';

interface PivotActionMenuProps {
  /** The related Resource, whose POST /action runs the pivot action. */
  resource: string;
  /** {parentUriKey}/{id}/{relationship}: the parent whose pivot rows the action changes. */
  via: string;
  actions: ActionMeta[];
  /** The ticked rows of the panel, by related key. */
  ids: (string | number)[];
  /** The query key of the panel's rows, reloaded after a run. */
  queryKey: unknown[];
}

const itemClass =
  'flex w-full items-start rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';

/** The pivot actions of a relation panel, run on its ticked rows through the parent's via. */
export function PivotActionMenu({ resource, via, actions, ids, queryKey }: PivotActionMenuProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const queryClient = useQueryClient();
  const menuId = useId();
  const [open, setOpen] = useState(false);
  const runner = useActionRunner({
    base: `${apiPath}/resources/${resource}`,
    onDone: () => {
      void queryClient.invalidateQueries({ queryKey });
    },
  });
  const selection: SelectionDescriptor = { mode: 'ids', ids, exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: null, lens: null, via };

  return (
    <div className="relative">
      <Button
        variant="outline"
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
        <div id={menuId} role="menu" aria-label={__('Actions')} tabIndex={-1} className="absolute start-0 top-full z-20 mt-1 w-56 rounded-lg border border-border bg-background p-1 shadow-lg">
          {actions.map((action) => (
            <button
              key={action.uriKey}
              type="button"
              role="menuitem"
              disabled={!action.standalone && ids.length === 0}
              className={cn(itemClass, action.destructive && 'text-destructive')}
              onClick={() => {
                setOpen(false);
                runner.start(action, selection, ids.length);
              }}
            >
              {action.name}
            </button>
          ))}
        </div>
      )}
      {runner.element}
    </div>
  );
}
