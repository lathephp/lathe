import { usePage } from '@inertiajs/react';
import { __panel, addShortcut, QueryClientProvider, queryClient, shortcutsEnabled, useTranslation } from '@lathephp/core';
import { Menu, PanelLeft, Search } from 'lucide-react';
import { lazy, Suspense, useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import { Button } from '../components/ui/Button';
import { cn } from '../lib/cn';
import type { SharedProps } from '../types';
import { SafeHtml } from '../components/SafeHtml';
import { Sidebar } from './Sidebar';

// The palette opens on demand, so it loads after first paint.
const SearchPalette = lazy(() => import('../palette/SearchPalette').then((module) => ({ default: module.SearchPalette })));

// Only an impersonating admin sees the banner, so no first load carries it.
const ImpersonationBanner = lazy(() => import('./ImpersonationBanner').then((module) => ({ default: module.ImpersonationBanner })));

const oneYear = 60 * 60 * 24 * 365;
const { RenderHook, Slot } = __panel;

// The bell loads beside the page, so no route's first load carries it.
const NotificationCenter = lazy(() => import('../notifications/NotificationCenter').then((module) => ({ default: module.NotificationCenter })));

/** The panel shell: sidebar, header, content and footer around a page. */
export function AppLayout({ children }: { children: ReactNode }) {
  const { __ } = useTranslation();
  const { props, url } = usePage<SharedProps>();
  const { layout, footer } = props;
  const collapsible = layout.sidebar.collapsible;
  const [open, setOpen] = useState(props.sidebarOpen);
  // The sheet belongs to the URL it opened on, so following a link closes it.
  const [sheetUrl, setSheetUrl] = useState<string | null>(null);
  const mobileOpen = sheetUrl === url;
  const expanded = collapsible === 'none' || open;
  const search = props.globalSearch;
  const notifications = props.notifications;
  const searchOn = search?.enabled === true;
  const [searching, setSearching] = useState(false);

  useEffect(() => {
    if (!searchOn) {
      return undefined;
    }

    return addShortcut('mod+k', () => {
      setSearching((was) => !was);
    });
  }, [searchOn]);

  function toggle() {
    const next = !open;
    setOpen(next);
    document.cookie = `sidebar_state=${String(next)}; path=/; max-age=${String(oneYear)}; SameSite=Lax`;
  }

  // The widths are config values, so they reach the CSS as custom properties on this one element.
  const widths = {
    '--sidebar-width': layout.sidebar.width,
    '--sidebar-width-icon': layout.sidebar.widthIcon,
  } as CSSProperties;

  return (
    <div data-lathe-shell className="flex min-h-svh bg-background text-foreground" style={widths}>
      <a
        href="#content"
        className="sr-only z-50 rounded-md bg-background px-3 py-2 text-sm focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:ring-2 focus:ring-ring"
      >
        {__('Skip to content')}
      </a>
      <RenderHook position="head" />
      {mobileOpen && (
        <button
          type="button"
          aria-label={__('Close menu')}
          className="fixed inset-0 z-30 bg-black/50 md:hidden"
          onClick={() => {
            setSheetUrl(null);
          }}
        />
      )}
      <Sidebar props={props} url={url} expanded={expanded} mobileOpen={mobileOpen} />
      <div className="flex min-w-0 flex-1 flex-col">
        {props.impersonating !== null && (
          // The placeholder holds the banner's height, so the page does not jump when it loads.
          <Suspense fallback={<div className="h-10" />}>
            <ImpersonationBanner name={props.impersonating.name} basePath={props.basePath} />
          </Suspense>
        )}
        <Slot name="shell.header">
          <header
            data-lathe-header
            className={cn(
              'flex h-14 items-center gap-2 border-b border-border bg-background px-4',
              layout.header.sticky && 'sticky top-0 z-20',
            )}
          >
            <Button
              variant="ghost"
              size="icon"
              className="md:hidden"
              aria-label={__('Open menu')}
              aria-controls="lathe-sidebar"
              aria-expanded={mobileOpen}
              onClick={() => {
                setSheetUrl(url);
              }}
            >
              <Menu aria-hidden="true" className="size-4" />
            </Button>
            {collapsible !== 'none' && (
              <Button
                variant="ghost"
                size="icon"
                className="max-md:hidden"
                aria-label={__('Toggle sidebar')}
                aria-controls="lathe-sidebar"
                aria-expanded={expanded}
                onClick={toggle}
              >
                <PanelLeft aria-hidden="true" className="size-4 rtl:rotate-180" />
              </Button>
            )}
            <div className="ms-auto flex items-center gap-2">
              {searchOn && layout.header.search && (
                <Button
                  variant="outline"
                  size="sm"
                  aria-label={__('Search')}
                  aria-keyshortcuts="Control+K Meta+K"
                  onClick={() => {
                    setSearching(true);
                  }}
                >
                  <Search aria-hidden="true" className="size-4" />
                  {shortcutsEnabled() && (
                    <kbd aria-hidden="true" className="text-xs text-muted-foreground max-md:hidden">
                      {/mac/i.test(navigator.platform) ? '⌘K' : 'Ctrl K'}
                    </kbd>
                  )}
                </Button>
              )}
              {notifications?.enabled === true && (
                <Suspense fallback={null}>
                  <NotificationCenter
                    basePath={props.basePath}
                    pollingInterval={notifications.pollingInterval}
                    showUnreadCount={notifications.showUnreadCount}
                  />
                </Suspense>
              )}
              <RenderHook position="header:end" />
            </div>
          </header>
        </Slot>
        <main id="content" data-lathe-content tabIndex={-1} className="flex-1 p-6 outline-none">
          <div className={cn(layout.content.width === 'contained' && 'mx-auto max-w-384')}>
            <RenderHook position="page:before" className="mb-6 flex flex-wrap gap-2" />
            {children}
            <RenderHook position="page:after" className="mt-6 flex flex-wrap gap-2" />
          </div>
        </main>
        {footer === null && <RenderHook position="footer:before" />}
        <Slot name="shell.footer" footer={footer}>
          {footer !== null && (
            <footer data-lathe-footer className="border-t border-border px-6 py-3 text-xs text-muted-foreground">
              {/* Inside the footer landmark, so what a hook adds belongs to a region. */}
              <RenderHook position="footer:before" />
              {'html' in footer ? (
                <SafeHtml html={footer.html} />
              ) : footer.href === null ? (
                footer.text
              ) : (
                <a href={footer.href} className="hover:underline">
                  {footer.text}
                </a>
              )}
            </footer>
          )}
        </Slot>
      </div>
      {searchOn && searching && (
        // The palette reads through the shared client even where no provider wraps the layout.
        <QueryClientProvider client={queryClient}>
          <Suspense fallback={null}>
            <SearchPalette
              basePath={props.basePath}
              debounce={search.debounce}
              onClose={() => {
                setSearching(false);
              }}
            />
          </Suspense>
        </QueryClientProvider>
      )}
    </div>
  );
}
