import type { SafeHtml } from '@lathephp/core';

/** The props the server shares with every panel page. */
export interface SharedProps {
  auth: { user: { name: string; email: string } | null };
  flash: { success: string | null; error: string | null };
  latheVersion: string;
  assetHash: string;
  navigation: NavigationSection[];
  sidebarOpen: boolean;
  appName: string;
  basePath: string;
  versionLabel: string | null;
  /** The default is a text and a link; a callback's HTML arrives sanitized. */
  footer: { text: string; href: string | null } | { html: SafeHtml } | null;
  /** The Menu::userMenu() items, then Security when the user has something to change. */
  userMenu: NavigationItem[];
  layout: {
    sidebar: { collapsible: 'icon' | 'offcanvas' | 'none'; width: string; widthIcon: string };
    header: { sticky: boolean };
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
}
