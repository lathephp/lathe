import { router } from '@inertiajs/react';
import { useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { ChevronDown } from 'lucide-react';
import { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react';
import { cn } from '../lib/cn';
import { recordSelection } from './selection';
import type { ActionMeta } from './types';
import { useActionRunner } from './useActionRunner';

const itemClass =
  'flex w-full items-start rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50';

/** The actions of the record a detail page shows, each enabled by its flag for that record. */
export function RecordActionMenu({ uriKey, id, actions }: { uriKey: string; id: string | number; actions: ActionMeta[] }) {
  const { __ } = useTranslation();
  const { apiPath } = useLathe();
  const menuId = useId();
  const [open, setOpen] = useState(false);
  // A menu that, ending at its button's end edge, would run out of the content (a phone, or under the
  // sidebar) opens from the button's start edge instead.
  const [fromStart, setFromStart] = useState(false);
  const trigger = useRef<HTMLButtonElement>(null);
  const menu = useRef<HTMLDivElement>(null);
  const runner = useActionRunner({
    base: `${apiPath}/resources/${uriKey}`,
    onDone: () => {
      router.reload();
    },
  });

  useLayoutEffect(() => {
    if (!open || fromStart || menu.current === null) {
      return;
    }

    const box = menu.current.getBoundingClientRect();
    const area = document.getElementById('content')?.getBoundingClientRect();

    if (box.left < (area?.left ?? 0) || box.right > (area?.right ?? window.innerWidth)) {
      setFromStart(true);
    }
  }, [open, fromStart]);

  // Its side was chosen for the width it opened at, so a new width (a phone turned on its side) closes
  // it. A new height alone, as a phone's address bar hides on scroll, keeps it open.
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const width = window.innerWidth;
    const close = () => {
      if (window.innerWidth !== width) {
        setOpen(false);
      }
    };
    window.addEventListener('resize', close);

    return () => {
      window.removeEventListener('resize', close);
    };
  }, [open]);

  if (actions.length === 0) {
    return null;
  }

  // On the menu and on Actions, which keeps the focus when the menu opens from a click.
  function closeOnEscape(event: KeyboardEvent) {
    if (open && event.key === 'Escape') {
      trigger.current?.focus();
      setOpen(false);
    }
  }

  return (
    <div className="relative">
      <Button
        ref={trigger}
        variant="outline"
        size="sm"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => {
          setFromStart(false);
          setOpen(!open);
        }}
        onKeyDown={closeOnEscape}
      >
        {__('Actions')}
        <ChevronDown aria-hidden="true" className="size-4" />
      </Button>
      {open && (
        <div
          id={menuId}
          role="menu"
          aria-label={__('Actions')}
          ref={menu}
          tabIndex={-1}
          className={cn('absolute top-full z-50 mt-1 w-64 rounded-lg border border-border bg-background p-1 shadow-lg', fromStart ? 'start-0' : 'end-0')}
          onKeyDown={closeOnEscape}
        >
          {actions.map((action) => (
            <button
              key={action.uriKey}
              type="button"
              role="menuitem"
              disabled={action.authorizedToRun === false}
              className={cn(itemClass, action.destructive && 'text-destructive')}
              onClick={() => {
                // The focus goes back to Actions as the menu closes, so a dialog the item opens returns it there.
                trigger.current?.focus();
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
