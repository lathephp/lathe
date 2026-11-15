import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useId, useRef, useState } from 'react';
import { menuClass, menuItemClass as itemClass, useMenuKeys } from '../lib/menuKeys';

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
          data-open=""
          data-side="bottom"
          className={`${menuClass} absolute end-0 top-full z-10 mt-1 w-52`}
        >
          <button
            type="button"
            role="menuitem"
            data-variant="default"
            className={itemClass}
            onClick={() => {
              setOpen(false);
              onRefresh();
            }}
          >
            {__('Refresh')}
          </button>
          {link !== null && (
            <Link role="menuitem" data-variant="default" href={link} className={itemClass}>
              {__('Open records')}
            </Link>
          )}
          {csv !== null && (
            <a
              role="menuitem"
              data-variant="default"
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
