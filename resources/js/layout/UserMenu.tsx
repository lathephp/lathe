import { Link, router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ChevronsUpDown, LogOut } from 'lucide-react';
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import type { NavigationItem } from '../types';

const AppearanceSwitcher = lazy(() => import('./AppearanceSwitcher').then((module) => ({ default: module.AppearanceSwitcher })));

interface UserMenuProps {
  user: { name: string; email: string };
  items: NavigationItem[];
  logoutPath: string;
  collapsed: boolean;
}

const rowClass = 'lathe-menu-row';

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
  'use no memo';

  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const avatar = (
    <span aria-hidden="true" className="lathe-avatar">
      {initials(user.name)}
    </span>
  );

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
    // It listens in the capture phase and claims the key, so the phone menu around it stays open.
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);
        trigger.current?.focus();
      }
    };
    document.addEventListener('pointerdown', onPointerDown);
    document.addEventListener('keydown', onKeyDown, true);

    return () => {
      document.removeEventListener('pointerdown', onPointerDown);
      document.removeEventListener('keydown', onKeyDown, true);
    };
  }, [open]);

  return (
    <div ref={root} className="relative">
      {open && (
        <div
          id="lathe-user-menu"
          role="menu"
          aria-label={__('User menu')}
          tabIndex={-1}
          className="lathe-user-menu"
        >
          <div role="none" className="lathe-user">
            {avatar}
            <span className="grid min-w-0">
              <span className="truncate font-semibold">{user.name}</span>
              <span className="truncate text-xs text-muted-foreground">{user.email}</span>
            </span>
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
              {item.icon !== null && <NavIcon name={item.icon} />}
              {item.label}
            </Link>
          ))}
          {/* Lazy: the switcher is only needed once the menu is open. The fallback has its final height. */}
          <div data-lathe-appearance-slot>
            <Suspense fallback={<div className="h-[4.75rem]" />}>
              <AppearanceSwitcher />
            </Suspense>
          </div>
          <button
            type="button"
            role="menuitem"
            className={rowClass}
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
        className="lathe-user lathe-user-trigger"
        onClick={() => {
          setOpen(!open);
        }}
      >
        {avatar}
        <span className={cn('grid min-w-0 flex-1', collapsed && 'md:sr-only')}>
          <span className="truncate font-medium text-foreground">{user.name}</span>
          <span className="truncate text-xs text-muted-foreground max-md:text-[13px]">{user.email}</span>
        </span>
        <ChevronsUpDown aria-hidden="true" className={cn('size-4 shrink-0 text-muted-foreground', collapsed && 'md:hidden')} />
      </button>
    </div>
  );
}
