import { Link, router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { LogOut } from 'lucide-react';
import { useState } from 'react';
import { cn } from '../lib/cn';
import type { NavigationItem } from '../types';

interface UserMenuProps {
  user: { name: string; email: string };
  items: NavigationItem[];
  basePath: string;
  collapsed: boolean;
}

const rowClass = 'flex items-center gap-2 rounded-md px-2 py-1.5 text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter((part) => part !== '')
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

/** The signed-in user at the foot of the sidebar, with the Log out entry. */
export function UserMenu({ user, items, basePath, collapsed }: UserMenuProps) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);

  return (
    <div className="relative border-t border-border p-2">
      {open && (
        <div
          id="lathe-user-menu"
          role="menu"
          aria-label={__('User menu')}
          tabIndex={-1}
          className="absolute start-2 bottom-full z-10 mb-2 w-56 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
        >
          <div role="none" className="px-2 py-1.5 text-sm">
            <p className="font-medium">{user.name}</p>
            <p className="text-muted-foreground">{user.email}</p>
          </div>
          {items.map((item) => (
            <Link key={item.href} href={item.href} role="menuitem" className={rowClass}>
              {item.label}
            </Link>
          ))}
          {/* The appearance switcher mounts here. */}
          <div data-lathe-appearance-slot />
          <button
            type="button"
            role="menuitem"
            className={cn('w-full', rowClass)}
            onClick={() => {
              router.post(`${basePath}/logout`);
            }}
          >
            <LogOut aria-hidden="true" className="size-4" />
            {__('Log out')}
          </button>
        </div>
      )}
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? 'lathe-user-menu' : undefined}
        className="flex w-full items-center gap-2 rounded-md p-1 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
        onClick={() => {
          setOpen(!open);
        }}
      >
        <span
          aria-hidden="true"
          className="grid size-8 shrink-0 place-items-center rounded-md bg-muted text-xs font-medium"
        >
          {initials(user.name)}
        </span>
        <span className={cn('truncate', collapsed && 'md:sr-only')}>{user.name}</span>
      </button>
    </div>
  );
}
