import { useTranslation } from '@lathephp/core';
import { cn } from '../lib/cn';
import type { SharedProps } from '../types';
import { NavSection, type NavBadge, type NavBlock } from './NavSection';
import { UserMenu } from './UserMenu';

interface SidebarProps {
  props: SharedProps;
  url: string;
  expanded: boolean;
  mobileOpen: boolean;
}

export function Sidebar({ props, url, expanded, mobileOpen }: SidebarProps) {
  const { __ } = useTranslation();
  const { appName, versionLabel, navigation, basePath, layout, auth } = props;
  const collapsed = !expanded;
  // Labels hide from the rail but stay for screen readers and the mobile sheet.
  const railHidden = collapsed && 'md:sr-only';
  const sections: NavBlock[] = navigation;
  const context = { url, basePath, collapsed, badges: (props.navigationBadges ?? {}) as Record<string, NavBadge> };

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
      <div data-lathe-hook="sidebar:before" />
      <nav aria-label={__('Main')} className="flex-1 overflow-y-auto p-2">
        {sections.map((section, index) => (
          <NavSection key={section.label ?? index} block={section} context={context} />
        ))}
      </nav>
      <div data-lathe-hook="sidebar:after" />
      {auth.user !== null && <UserMenu user={auth.user} basePath={basePath} collapsed={collapsed} />}
    </aside>
  );
}
