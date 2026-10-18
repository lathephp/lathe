import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useEffect, useId, useRef, useState } from 'react';

const itemClass =
  'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

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
    <div ref={root} className="relative -me-1 shrink-0">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Menu for :title', { title: name })}
        className="grid size-7 place-items-center rounded-md text-muted-foreground outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
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
          className="absolute end-0 top-full z-10 mt-1 w-44 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
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
