import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { useId, useRef, useState } from 'react';
import { buttonClass } from '../components/ui/Button';
import { DropdownMenuItem, DropdownMenuTrigger } from '../components/ui/DropdownMenu';
import { menuClasses, useMenuKeys } from '../lib/menuKeys';

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
      <DropdownMenuTrigger
        open={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Menu for :title', { title: name })}
        className={`${buttonClass('ghost', 'icon-xs')} text-muted-foreground`}
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
      </DropdownMenuTrigger>
      {open && (
        <div
          id={menuId}
          role="menu"
          tabIndex={-1}
          data-open=""
          data-side="bottom"
          className={`${menuClasses.content} absolute end-0 top-full z-10 mt-1 w-52`}
        >
          <DropdownMenuItem
            onClick={() => {
              setOpen(false);
              onRefresh();
            }}
          >
            {__('Refresh')}
          </DropdownMenuItem>
          {link !== null && (
            <Link role="menuitem" data-variant="default" href={link} className={menuClasses.item}>
              {__('Open records')}
            </Link>
          )}
          {csv !== null && (
            <a
              role="menuitem"
              data-variant="default"
              href={csv}
              download
              className={menuClasses.item}
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
