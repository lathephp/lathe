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
  footer: { text: string; href: string | null } | null;
  layout: {
    sidebar: { collapsible: 'icon' | 'offcanvas' | 'none'; width: string; widthIcon: string };
    header: { sticky: boolean };
    content: { width: 'full' | 'contained' };
  };
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
