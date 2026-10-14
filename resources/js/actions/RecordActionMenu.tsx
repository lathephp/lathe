import { router } from '@inertiajs/react';
import { useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import type { SelectionDescriptor } from '../index/useRowSelection';
import { cn } from '../lib/cn';
import type { ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';

/** The selection of one record, trashed or not. */
export function recordSelection(id: string | number): SelectionDescriptor {
  return { mode: 'ids', ids: [id], exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: 'with', lens: null, via: null };
}

const itemClass =
  'flex w-full items-start rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';

/** The actions of the record a detail page shows, each enabled by its flag for that record. */
export function RecordActionMenu({ uriKey, id, actions }: { uriKey: string; id: string | number; actions: ActionMeta[] }) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const menuId = useId();
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
          className="absolute end-0 top-full z-20 mt-1 w-64 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
        >
          {actions.map((action) => (
            <button
              key={action.uriKey}
              type="button"
              role="menuitem"
              disabled={action.authorizedToRun === false}
              className={cn(itemClass, action.destructive && 'text-destructive')}
              onClick={() => {
                setOpen(false);
                runner.start(action, recordSelection(id), 1);
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
