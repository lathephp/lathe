import { App } from '@inertiajs/react';
import { render } from '@testing-library/react';
import type { ComponentProps, ComponentType } from 'react';
import { panelLayout } from '../layout/panelLayout';
import type { SharedProps } from '../types';

type InitialPage = ComponentProps<typeof App>['initialPage'];

const pages = import.meta.glob<{ default: ComponentType }>('../pages/**/*.tsx', { eager: true });

/** The shared props of the contract, as a signed-in visit to the workbench sends them. */
export function sharedProps(overrides: Partial<SharedProps> = {}): SharedProps {
  return {
    auth: { user: { name: 'Ada Lovelace', email: 'ada@example.com' } },
    flash: { success: null, error: null },
    latheVersion: '1.0.0',
    assetHash: 'abc123',
    navigation: [
      { label: null, icon: null, items: [{ label: 'Dashboard', href: '/lathe', icon: 'LayoutDashboard' }] },
      { label: 'Resources', icon: null, items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: 'ShoppingCart' }] },
      { label: 'Heartbeat', icon: null, items: [{ label: 'Heartbeat', href: '/lathe/plugins/heartbeat', icon: 'Activity' }] },
    ],
    userMenu: [],
    sidebarOpen: true,
    appName: 'Acme',
    basePath: '/lathe',
    versionLabel: 'v1.0.0',
    footer: { text: 'Powered by Lathe', href: 'https://lathephp.com' },
    layout: {
      sidebar: { collapsible: 'icon', width: '16rem', widthIcon: '3rem' },
      header: { sticky: true },
      content: { width: 'full' },
    },
    tableStyle: { density: 'comfortable', striped: false },
    ...overrides,
  };
}

export interface RenderPageOptions {
  shared?: Partial<SharedProps>;
  url?: string;
  page?: Partial<InitialPage>;
}

/** Render a panel page the way the browser does: through Inertia's App with the panel layout. */
export function renderPage(component: string, props: Record<string, unknown>, options: RenderPageOptions = {}) {
  const Component = pages[`../pages/${component}.tsx`]?.default;

  if (Component === undefined) {
    throw new Error(`No page named "${component}".`);
  }

  const initialPage = {
    component,
    props: { errors: {}, ...sharedProps(options.shared), ...props },
    url: options.url ?? '/lathe',
    version: null,
    rescuedProps: [],
    flash: {},
    rememberedState: {},
    ...options.page,
  } as InitialPage;

  return render(
    <App
      initialPage={initialPage}
      initialComponent={Component}
      resolveComponent={() => Component}
      defaultLayout={panelLayout}
    />,
  );
}

/** Every text node and accessible label on screen, trimmed, in document order. */
export function visibleStrings(root: HTMLElement = document.body): string[] {
  const strings: string[] = [];
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);

  for (let node = walker.nextNode(); node !== null; node = walker.nextNode()) {
    const text = node.textContent?.trim() ?? '';

    if (text !== '') {
      strings.push(text);
    }
  }

  root.querySelectorAll('[aria-label]').forEach((element) => {
    strings.push(element.getAttribute('aria-label') ?? '');
  });

  return strings;
}

export const bracketed = (key: string, replace: Record<string, string | number> = {}) =>
  `[${Object.entries(replace).reduce((text, [name, value]) => text.replaceAll(`:${name}`, String(value)), key)}]`;
