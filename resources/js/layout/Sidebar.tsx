import { Link } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import type { SharedProps } from '../types';
import { UserMenu } from './UserMenu';

interface SidebarProps {
  props: SharedProps;
  url: string;
  expanded: boolean;
  mobileOpen: boolean;
}

/** The dashboard link is active on its own path only; any other link also on the paths below it. */
function isActive(href: string, url: string, basePath: string): boolean {
  const path = url.split(/[?#]/)[0] ?? url;

  return href === basePath ? path === href : path === href || path.startsWith(`${href}/`);
}

export function Sidebar({ props, url, expanded, mobileOpen }: SidebarProps) {
  const { __ } = useTranslation();
  const { appName, versionLabel, navigation, basePath, layout, auth } = props;
  const collapsed = !expanded;
  // Labels hide from the rail but stay for screen readers and the mobile sheet.
  const railHidden = collapsed && 'md:sr-only';

  return (
    <aside
      id="lathe-sidebar"
      aria-label={__('Sidebar')}
      data-state={expanded ? 'expanded' : 'collapsed'}
      data-mobile={mobileOpen ? 'open' : 'closed'}
      className={cn(
        'fixed inset-y-0 start-0 z-40 flex w-(--sidebar-width) flex-col border-e border-border bg-muted transition-[width,translate] md:sticky md:top-0 md:h-svh md:translate-x-0',
        !mobileOpen && 'max-md:-translate-x-full max-md:rtl:translate-x-full',
        expanded && 'md:w-(--sidebar-width)',
        collapsed && layout.sidebar.collapsible === 'icon' && 'md:w-(--sidebar-width-icon)',
        collapsed && layout.sidebar.collapsible === 'offcanvas' && 'md:w-0 md:overflow-hidden md:border-0',
      )}
    >
      <div className="flex items-center gap-2 p-2">
        <span
          aria-hidden="true"
          className="grid size-8 shrink-0 place-items-center rounded-md bg-primary text-sm font-semibold text-primary-foreground"
        >
          {appName.charAt(0).toUpperCase()}
        </span>
        <div className={cn('min-w-0', railHidden)}>
          <p className="truncate text-sm font-semibold">{appName}</p>
          {versionLabel !== null && <p className="truncate text-xs text-muted-foreground">{versionLabel}</p>}
        </div>
      </div>
      <nav aria-label={__('Main')} className="flex-1 overflow-y-auto p-2">
        {navigation.map((section, index) => (
          <div key={section.label ?? index} className="mb-4">
            {section.label !== null && (
              <h2 className={cn('px-2 pb-1 text-xs font-medium text-muted-foreground', railHidden)}>{section.label}</h2>
            )}
            <ul className="space-y-0.5">
              {section.items.map((item) => (
                <li key={item.href}>
                  <Link
                    href={item.href}
                    aria-current={isActive(item.href, url, basePath) ? 'page' : undefined}
                    className="flex h-8 items-center gap-2 rounded-md px-2 text-sm outline-none hover:bg-background focus-visible:ring-2 focus-visible:ring-ring aria-[current=page]:bg-background aria-[current=page]:font-medium"
                  >
                    <NavIcon name={item.icon} />
                    <span className={cn('truncate', railHidden)}>{item.label}</span>
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
      {auth.user !== null && <UserMenu user={auth.user} basePath={basePath} collapsed={collapsed} />}
    </aside>
  );
}
