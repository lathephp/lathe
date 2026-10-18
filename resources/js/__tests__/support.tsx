import { App } from '@inertiajs/react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { expect, vi } from 'vitest';
import type { ComponentProps, ComponentType } from 'react';
import { panelLayout } from '../layout/panelLayout';
import type { SharedProps } from '../types';

type InitialPage = ComponentProps<typeof App>['initialPage'];

const pages = import.meta.glob<{ default: ComponentType }>('../pages/**/*.tsx', { eager: true });

/** The shared props of the contract, as a signed-in visit to the workbench sends them. */
export function sharedProps(overrides: Partial<SharedProps> = {}): SharedProps {
  return {
    auth: { user: { name: 'Ada Lovelace', email: 'ada@example.com' } },
    impersonating: null,
    authPaths: { login: '/lathe/login', logout: '/lathe/logout', forgotPassword: null, resetPassword: null },
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
    brand: null,
    basePath: '/lathe',
    versionLabel: 'v1.0.0',
    footer: { text: 'Powered by Lathe', href: 'https://lathephp.com' },
    layout: {
      sidebar: { collapsible: 'icon', width: '16rem', widthIcon: '3rem' },
      header: { sticky: true, search: true },
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
    // The server names its shared props, so an instant visit keeps them for the shell.
    sharedProps: Object.keys(sharedProps(options.shared)),
    ...options.page,
  } as InitialPage;

  return render(
    <App
      initialPage={initialPage}
      initialComponent={Component}
      resolveComponent={(name) => {
        const page = pages[`../pages/${name}.tsx`]?.default;

        if (page === undefined) {
          throw new Error(`No page named "${name}".`);
        }

        return page;
      }}
      defaultLayout={panelLayout}
    />,
  );
}

/** One request Inertia sent through the fake XMLHttpRequest, answered when the test says so. */
export interface FakeRequest {
  method: string;
  url: string;
  headers: Record<string, string>;
  /** Answers with this Inertia page, as the panel's server would. */
  respond: (status: number, page: { component: string; props: Record<string, unknown>; url: string }) => void;
}

/**
 * Stands in for the browser's XMLHttpRequest, so a test drives real Inertia visits: each request
 * waits in the list until the test answers it. Undo it with vi.unstubAllGlobals().
 */
export function fakeInertiaServer(): FakeRequest[] {
  const requests: FakeRequest[] = [];

  class FakeXhr {
    status = 0;
    responseText = '';
    upload = {};
    onload: (() => void) | null = null;
    onerror: (() => void) | null = null;
    onabort: (() => void) | null = null;
    private method = 'GET';
    private url = '';
    private headers: Record<string, string> = {};
    private responseHeaders = '';

    open(method: string, url: string) {
      this.method = method;
      this.url = url;
    }

    setRequestHeader(name: string, value: string) {
      this.headers[name] = value;
    }

    getAllResponseHeaders() {
      return this.responseHeaders;
    }

    abort() {
      this.onabort?.();
    }

    send() {
      requests.push({
        method: this.method,
        url: this.url,
        headers: this.headers,
        respond: (status, page) => {
          this.status = status;
          this.responseHeaders = 'content-type: application/json\r\nx-inertia: true';
          this.responseText = JSON.stringify({
            version: null,
            flash: {},
            rescuedProps: [],
            sharedProps: Object.keys(sharedProps()),
            ...page,
            props: { errors: {}, ...sharedProps(), ...page.props },
          });
          this.onload?.();
        },
      });
    }
  }

  vi.stubGlobal('XMLHttpRequest', FakeXhr);
  // A visit in an earlier test moved the address, and Inertia never prefetches the page it is on.
  window.history.replaceState(null, '', '/');

  return requests;
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

/** Opens a panel Select and picks the option with this label, as a person would with the pointer. */
export async function choose(trigger: HTMLElement, label: string): Promise<void> {
  fireEvent.mouseDown(trigger, { button: 0 });
  fireEvent.mouseUp(trigger, { button: 0 });
  const option = await screen.findByRole('option', { name: label });

  // Base UI ignores a click in the first moments after the press that opened the list.
  await waitFor(() => {
    fireEvent.click(option);
    expect(screen.queryByRole('listbox')).toBeNull();
  });
}

/** Opens a panel Select, reads its option labels and closes it with Escape. */
export async function optionLabels(trigger: HTMLElement): Promise<string[]> {
  fireEvent.mouseDown(trigger, { button: 0 });
  fireEvent.mouseUp(trigger, { button: 0 });
  const list = await screen.findByRole('listbox');
  const labels = screen.getAllByRole('option').map((option) => option.textContent);
  fireEvent.keyDown(list, { key: 'Escape' });
  await waitFor(() => {
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  return labels;
}

/**
 * Presses a panel file picker and chooses this file in the dialog it opens. The picker makes
 * its file input in code, so the spy on click catches that input in place of the dialog.
 */
export function pickFile(button: HTMLElement, file: File): HTMLInputElement {
  const click = vi.spyOn(HTMLInputElement.prototype, 'click').mockImplementation(() => undefined);
  fireEvent.click(button);
  const input = click.mock.contexts[0] as HTMLInputElement | undefined;
  click.mockRestore();

  if (input === undefined) {
    throw new Error('The button opened no file dialog.');
  }

  fireEvent.change(input, { target: { files: [file] } });

  return input;
}
