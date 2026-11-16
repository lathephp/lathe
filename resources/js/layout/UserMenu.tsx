import { Link, router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ChevronsUpDown, LogOut } from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import { showKeys } from '../lib/keys';
import type { NavigationItem } from '../types';
import { DropdownMenuItem, DropdownMenuTrigger } from '../components/ui/DropdownMenu';
import { menuClasses, useMenuKeys } from '../lib/menuKeys';

const AppearanceSwitcher = lazy(() => import('./AppearanceSwitcher').then((module) => ({ default: module.AppearanceSwitcher })));

interface UserMenuProps {
  user: { name: string; email: string };
  items: NavigationItem[];
  logoutPath: string;
  collapsed: boolean;
}

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
  // The shell renders once per visit, so the compiler's cache would only add first-load bytes.
  'use no memo';

  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const avatar = (
    <span aria-hidden="true" className="lathe-avatar">
      {initials(user.name)}
    </span>
  );

  // Escape inside the menu is claimed there, so the phone menu around it stays open.
  useMenuKeys(root, open, () => {
    setOpen(false);
  });

  return (
    <div ref={root} className="relative">
      {open && (
        <div
          id="lathe-user-menu"
          role="menu"
          aria-label={__('User menu')}
          tabIndex={-1}
          data-open=""
          data-side="top"
          className={`${menuClasses.content} absolute inset-x-2 bottom-full z-10 flex flex-col`}
        >
          <div role="none" className="lathe-user border-b border-border pb-3">
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
              data-variant="default"
              className={menuClasses.item}
              onClick={() => {
                setOpen(false);
              }}
            >
              {item.icon !== null && <NavIcon name={item.icon} />}
              {item.label}
            </Link>
          ))}
          <DropdownMenuItem
            onClick={() => {
              setOpen(false);
              showKeys();
            }}
          >
            {__('Keyboard shortcuts')}
          </DropdownMenuItem>
          {/* Lazy: the switcher is only needed once the menu is open. The fallback has its final height. */}
          <div data-lathe-appearance-slot>
            <Suspense fallback={<div className="h-[4.75rem]" />}>
              <AppearanceSwitcher />
            </Suspense>
          </div>
          <DropdownMenuItem
            onClick={() => {
              router.post(logoutPath);
            }}
          >
            <LogOut aria-hidden="true" className="size-4" />
            {__('Sign out')}
          </DropdownMenuItem>
        </div>
      )}
      <DropdownMenuTrigger
        open={open}
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
      </DropdownMenuTrigger>
    </div>
  );
}
