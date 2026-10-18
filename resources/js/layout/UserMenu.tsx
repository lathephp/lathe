import { Link, router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { LogOut } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { cn } from '../lib/cn';
import type { NavigationItem } from '../types';

const AppearanceSwitcher = lazy(() => import('./AppearanceSwitcher').then((module) => ({ default: module.AppearanceSwitcher })));

interface UserMenuProps {
  user: { name: string; email: string };
  items: NavigationItem[];
  logoutPath: string;
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

/** The signed-in user at the foot of the sidebar, with the Sign out entry. */
export function UserMenu({ user, items, logoutPath, collapsed }: UserMenuProps) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const onPointerDown = (event: PointerEvent) => {
      if (event.target instanceof Node && root.current?.contains(event.target) !== true) {
        setOpen(false);
      }
    };
    // Escape closes the menu wherever the focus is, the trigger included, and hands it back there.
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
        trigger.current?.focus();
      }
    };
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);

  return (
    <div ref={root} className="relative border-t border-sidebar-border p-2">
      {open && (
        <div
          id="lathe-user-menu"
          role="menu"
          aria-label={__('User menu')}
          tabIndex={-1}
          className="absolute start-2 bottom-full z-10 mb-2 w-56 rounded-lg border border-border bg-background p-1 shadow-lg"
        >
          <div role="none" className="px-2 py-1.5 text-sm">
            <p className="font-medium">{user.name}</p>
            <p className="text-muted-foreground">{user.email}</p>
          </div>
          {items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              role="menuitem"
              className={rowClass}
              onClick={() => {
                setOpen(false);
              }}
            >
              {item.label}
            </Link>
          ))}
          {/* Lazy: the switcher is only needed once the menu is open. The fallback has its final height. */}
          <div data-lathe-appearance-slot>
            <Suspense fallback={<div className="h-[6.625rem]" />}>
              <AppearanceSwitcher />
            </Suspense>
          </div>
          <button
            type="button"
            role="menuitem"
            className={cn('w-full', rowClass)}
            onClick={() => {
              router.post(logoutPath);
            }}
          >
            <LogOut aria-hidden="true" className="size-4" />
            {__('Sign out')}
          </button>
        </div>
      )}
      <button
        ref={trigger}
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
        <span className={cn('grid min-w-0', collapsed && 'md:sr-only')}>
          <span className="truncate font-medium">{user.name}</span>
          <span className="truncate text-xs text-muted-foreground">{user.email}</span>
        </span>
      </button>
    </div>
  );
}
