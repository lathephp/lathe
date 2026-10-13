import { usePage } from '@inertiajs/react';
import { addShortcut, QueryClientProvider, queryClient, shortcutsEnabled, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { Menu, PanelLeft, Search } from 'lucide-react';
import { useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import { cn } from '../lib/cn';
import { SearchPalette } from '../palette/SearchPalette';
import type { SharedProps } from '../types';
import { Sidebar } from './Sidebar';

const oneYear = 60 * 60 * 24 * 365;

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
    <div className="flex min-h-svh bg-background text-foreground" style={widths}>
      <a
        href="#content"
        className="sr-only z-50 rounded-md bg-background px-3 py-2 text-sm focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:ring-2 focus:ring-ring"
      >
        {__('Skip to content')}
      </a>
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
        <header
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
              <PanelLeft aria-hidden="true" className="size-4" />
            </Button>
          )}
          {searchOn && (
            <Button
              variant="outline"
              size="sm"
              className="ms-auto"
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
        </header>
        <main id="content" tabIndex={-1} className="flex-1 p-6 outline-none">
          <div className={cn(layout.content.width === 'contained' && 'mx-auto max-w-384')}>{children}</div>
        </main>
        {footer !== null && (
          <footer className="border-t border-border px-6 py-3 text-xs text-muted-foreground">
            {footer.href === null ? (
              footer.text
            ) : (
              <a href={footer.href} className="hover:underline">
                {footer.text}
              </a>
            )}
          </footer>
        )}
      </div>
      {searchOn && searching && (
        // The palette reads through the shared client even where no provider wraps the layout.
        <QueryClientProvider client={queryClient}>
          <SearchPalette
            basePath={props.basePath}
            debounce={search.debounce}
            onClose={() => {
              setSearching(false);
            }}
          />
        </QueryClientProvider>
      )}
    </div>
  );
}
