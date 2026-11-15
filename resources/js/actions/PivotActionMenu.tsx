import { useLathe, useQueryClient, useTranslation } from '@lathephp/core';
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import type { SelectionDescriptor } from '../index/useRowSelection';
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

/** The pivot actions of a relation panel, run on its ticked rows through the parent's via. */
export function PivotActionMenu({ resource, via, actions, ids, queryKey }: PivotActionMenuProps) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const queryClient = useQueryClient();
  const runner = useActionRunner({
    base: `${apiPath}/resources/${resource}`,
    onDone: () => {
      void queryClient.invalidateQueries({ queryKey });
    },
  });
  const selection: SelectionDescriptor = { mode: 'ids', ids, exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: null, lens: null, via };
  // With no row ticked only a standalone action can run; the runner stays for a run still showing.
  const shown = ids.length > 0 ? actions : actions.filter((action) => action.standalone);

  if (shown.length === 0) {
    return runner.element;
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger render={<Button variant="outline" />}>
          {__('Actions')}
          <ChevronDown aria-hidden="true" className="size-4" />
        </DropdownMenuTrigger>
        {/* On the body, above the sidebar; it starts at the button's start edge and moves in to stay 8 px inside the window. */}
        <DropdownMenuContent align="start" collisionPadding={8} aria-label={__('Actions')} className="w-56">
          {shown.map((action) => (
            <DropdownMenuItem
              key={action.uriKey}
              variant={action.destructive ? 'destructive' : 'default'}
              onClick={() => {
                runner.start(action, selection, ids.length);
              }}
            >
              {action.name}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
      {runner.element}
    </>
  );
}
