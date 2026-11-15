import { router } from '@inertiajs/react';
import { useLathe, useTranslation } from '@lathephp/core';
import { Button, DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { recordSelection } from './selection';
import type { ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';

/** The page's content beside the sidebar, which the menu stays inside; the window when there is none. */
function content(): Element | undefined {
  return document.getElementById('content') ?? undefined;
}

/** The actions of the record a detail page shows, each enabled by its flag for that record. */
export function RecordActionMenu({ uriKey, id, actions }: { uriKey: string; id: string | number; actions: ActionMeta[] }) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  // Held here so the menu renders again as it opens, when the content it stays inside is on the page.
  const [open, setOpen] = useState(false);
  const runner = useActionRunner({
    base: `${apiPath}/resources/${uriKey}`,
    onDone: () => {
      router.reload();
    },
  });

  if (actions.length === 0) {
    return null;
  }

  return (
    <>
      <DropdownMenu open={open} onOpenChange={setOpen}>
        <DropdownMenuTrigger render={<Button variant="outline" size="sm" />}>
          {__('Actions')}
          <ChevronDown aria-hidden="true" className="size-4" />
        </DropdownMenuTrigger>
        {/* On the body, above the sidebar; it ends at the button's end edge and moves in to stay 8 px inside the content, so it never covers the sidebar. */}
        <DropdownMenuContent align="end" collisionPadding={8} collisionBoundary={open ? content() : undefined} aria-label={__('Actions')} className="w-64">
          {actions.map((action) => (
            <DropdownMenuItem
              key={action.uriKey}
              variant={action.destructive ? 'destructive' : 'default'}
              disabled={action.authorizedToRun === false}
              onClick={() => {
                runner.start(action, recordSelection(id), 1);
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
