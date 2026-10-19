import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useId, useRef, useState } from 'react';
import { useMenuKeys } from './menuKeys';

const itemClass =
  'flex h-8 w-full items-center rounded-md px-2 text-start text-sm text-popover-foreground outline-none hover:bg-accent focus-visible:bg-accent';

interface WidgetMenuProps {
  name: string;
  /** Refetches the card. */
  onRefresh: () => void;
  /** Where Open records goes; null leaves it out. */
  link: string | null;
  /** The card's CSV download; null leaves Download CSV out. */
  csv: string | null;
}

/** A dashboard card's ⋯ menu: Refresh, Open records and Download CSV. */
export function WidgetMenu({ name, onRefresh, link, csv }: WidgetMenuProps) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const menuId = useId();
  const root = useRef<HTMLDivElement>(null);
  useMenuKeys(root, open, () => {
    setOpen(false);
  });

  // A press anywhere outside the menu closes it.
  useEffect(() => {
    if (!open) {
      return;
    }

    const close = (event: PointerEvent) => {
      if (!(event.target instanceof Node) || root.current?.contains(event.target) !== true) {
        setOpen(false);
      }
    };

    document.addEventListener('pointerdown', close);

    return () => {
      document.removeEventListener('pointerdown', close);
    };
  }, [open]);

  return (
    <div ref={root} className="relative -my-1 -me-1 shrink-0">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Menu for :title', { title: name })}
        className="grid size-[26px] place-items-center rounded-md text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-focus"
        onClick={() => {
          setOpen(!open);
        }}
      >
        {/* Drawn here: lucide's Ellipsis would split into a chunk the index's first load then fetches. */}
        <svg aria-hidden="true" viewBox="0 0 24 24" className="size-4 fill-current">
          <circle cx="5" cy="12" r="1.75" />
          <circle cx="12" cy="12" r="1.75" />
          <circle cx="19" cy="12" r="1.75" />
        </svg>
      </button>
      {open && (
        <div
          id={menuId}
          role="menu"
          tabIndex={-1}
          className="absolute end-0 top-full z-10 mt-1 w-52 rounded-[12px] border border-border bg-popover p-1 shadow-md"
        >
          <button
            type="button"
            role="menuitem"
            className={itemClass}
            onClick={() => {
              setOpen(false);
              onRefresh();
            }}
          >
            {__('Refresh')}
          </button>
          {link !== null && (
            <Link role="menuitem" href={link} className={itemClass}>
              {__('Open records')}
            </Link>
          )}
          {csv !== null && (
            <a
              role="menuitem"
              href={csv}
              download
              className={itemClass}
              onClick={() => {
                setOpen(false);
              }}
            >
              {__('Download CSV')}
            </a>
          )}
        </div>
      )}
    </div>
  );
}
