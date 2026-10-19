import { __panel, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useState } from 'react';
import { cn } from '../lib/cn';
import type { SharedProps } from '../types';
import { NavSection, type NavBadge, type NavBlock } from './NavSection';
import { UserMenu } from './UserMenu';

const { RenderHook } = __panel;

interface SidebarProps {
  props: SharedProps;
  url: string;
  expanded: boolean;
  mobileOpen: boolean;
  onClose: () => void;
}

export function Sidebar({ props, url, expanded, mobileOpen, onClose }: SidebarProps) {
  // The shell renders once per visit, so the compiler's cache would only add first-load bytes.
  'use no memo';

  const { __ } = useTranslation();
  const { appName, brand, versionLabel, navigation, basePath, layout, auth } = props;
  const collapsed = !expanded;
  // Labels hide from the rail but stay for screen readers and the mobile sheet.
  const railHidden = collapsed && 'md:sr-only';
  const sections: NavBlock[] = navigation;
  // The pressed link is the active one until the visit it starts lands somewhere.
  const [pressed, setPressed] = useState<{ href: string; from: string } | null>(null);

  if (pressed !== null && pressed.from !== url) {
    setPressed(null);
  }

  const context = {
    url: pressed?.from === url ? pressed.href : url,
    basePath,
    collapsed,
    badges: (props.navigationBadges ?? {}) as Record<string, NavBadge>,
    press: (href: string | null) => {
      setPressed(href === null ? null : { href, from: url });
    },
  };

  return (
    <aside
      id="lathe-sidebar"
      data-lathe-sidebar
      aria-label={__('Sidebar')}
      data-state={expanded ? 'expanded' : 'collapsed'}
      data-mobile={mobileOpen ? 'open' : 'closed'}
      className={cn(
        'fixed inset-y-0 start-0 z-40 flex w-(--sidebar-width) flex-col max-md:w-76 border-e border-sidebar-border bg-sidebar text-sidebar-foreground transition-[width,translate] md:sticky md:top-0 md:h-svh md:translate-x-0',
        mobileOpen ? 'shadow-lg' : 'max-md:-translate-x-full max-md:rtl:translate-x-full',
        expanded && 'md:w-(--sidebar-width)',
        collapsed && layout.sidebar.collapsible === 'icon' && 'md:w-(--sidebar-width-icon)',
        collapsed && layout.sidebar.collapsible === 'offcanvas' && 'md:w-0 md:overflow-hidden md:border-0',
      )}
    >
      <div className="lathe-sidebar-brand">
        {brand ? (
          <>
            <img src={brand.logo} alt={appName} className={cn('size-7 shrink-0 object-contain', brand.logoDark && 'dark:hidden')} />
            {brand.logoDark && <img src={brand.logoDark} alt={appName} className="hidden size-7 shrink-0 object-contain dark:block" />}
          </>
        ) : (
          <span aria-hidden="true" className="lathe-sidebar-mark">
            {appName.charAt(0).toUpperCase()}
          </span>
        )}
        <div className={cn('min-w-0', railHidden)}>
          <p className="truncate text-sm leading-5 font-semibold text-foreground max-md:text-[15px]">{appName}</p>
          {versionLabel !== null && <p className="truncate text-xs text-muted-foreground">{versionLabel}</p>}
        </div>
        {mobileOpen && (
          <button
            type="button"
            aria-label={__('Close menu')}
            className="ms-auto grid size-11 shrink-0 place-items-center rounded-md text-muted-foreground outline-none hover:bg-sidebar-hover focus-visible:ring-2 focus-visible:ring-ring md:hidden"
            onClick={onClose}
          >
            <X aria-hidden="true" className="size-4" />
          </button>
        )}
      </div>
      <RenderHook position="sidebar:before" />
      <nav aria-label={__('Main')} className="flex flex-1 flex-col gap-4 overflow-y-auto px-2 py-3 max-md:gap-3.5">
        {sections.map((section, index) => (
          <NavSection key={section.label ?? index} block={section} context={context} />
        ))}
      </nav>
      <RenderHook position="sidebar:after" />
      {auth.user !== null && <UserMenu user={auth.user} items={props.userMenu} logoutPath={props.authPaths.logout ?? `${basePath}/logout`} collapsed={collapsed} />}
    </aside>
  );
}
