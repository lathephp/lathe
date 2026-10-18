import type { SafeHtml } from '@lathephp/core';

/** The props the server shares with every panel page. */
export interface SharedProps {
  auth: { user: { name: string; email: string } | null };
  /** Who the signed-in admin is impersonating, or null when no impersonation runs. */
  impersonating: { name: string } | null;
  /** The panel's sign-in routes, or the host's paths when the panel's are off. */
  authPaths: { login: string | null; logout: string | null; forgotPassword: string | null; resetPassword: string | null };
  flash: { success: string | null; error: string | null };
  latheVersion: string;
  assetHash: string;
  navigation: NavigationSection[];
  sidebarOpen: boolean;
  appName: string;
  /** Null shows the app name as text; a logo shows beside the name. */
  brand: { logo: string; logoDark: string | null } | null;
  basePath: string;
  versionLabel: string | null;
  /** The default is a text and a link; a callback's HTML arrives sanitized. */
  footer: { text: string; href: string | null } | { html: SafeHtml } | null;
  /** The Menu::userMenu() items, then Security when the user has something to change. */
  userMenu: NavigationItem[];
  layout: {
    sidebar: { collapsible: 'icon' | 'offcanvas' | 'none'; width: string; widthIcon: string };
    header: { sticky: boolean; search: boolean };
    content: { width: 'full' | 'contained' };
  };
  /** Absent means off. The server sends it once the config and the user's ability allow the palette. */
  globalSearch?: { enabled: boolean; debounce: number };
  /** Absent means off. The server sends the notifications config. */
  notifications?: { enabled: boolean; pollingInterval: number; showUnreadCount: boolean };
  tableStyle: { density: 'compact' | 'comfortable' | 'spacious'; striped: boolean };
  [key: string]: unknown;
}

export interface NavigationSection {
  label: string | null;
  icon: string | null;
  items: NavigationItem[];
}

export interface NavigationItem {
  label: string;
  href: string;
  icon: string | null;
  /** The Inertia page the link opens, sent for a resource index or a dashboard. */
  component?: string;
  /** The prefetch cache tag a write to the link's resource flushes: resource:<uriKey>. */
  cacheTag?: string;
  /** Whether the resource index draws a tab band and an action bar, so its skeleton draws them too. */
  skeleton?: IndexSkeletonHint;
}

export interface IndexSkeletonHint {
  tabs: boolean;
  actions: boolean;
}
