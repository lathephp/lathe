import { Head } from '@inertiajs/react';
import { renderHook, type RenderHookProps } from '@lathephp/core';
import { screen, waitFor } from '@testing-library/react';
import { lazy } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false }, widgets: [] };
const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

function renderIndex(uriKey: string) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey, label: 'Things', singularLabel: 'Thing', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { columns: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 0, exact: true, capped: false, estimated: false },
    },
    { url: `/lathe/resources/${uriKey}` },
  );
}

function renderDetail() {
  return renderPage('Resources/Detail', {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } },
    record: { id: 12, title: 'ORD-00012', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
    breadcrumbs: [],
  });
}

function hook(position: string): HTMLElement | null {
  return document.querySelector<HTMLElement>(`[data-lathe-hook="${position}"]`);
}

/** The hook list loads in its own chunk, so its text arrives a tick after the wrapper. */
async function shows(position: string, text: string): Promise<void> {
  await waitFor(() => {
    expect(hook(position)?.textContent).toBe(text);
  });
}

const Badge = (text: string) => () => <span>{text}</span>;

describe('render hooks in the shell', () => {
  it('renders every hook of a position in the order they were added', async () => {
    renderHook('page:before', Badge('first'));
    renderHook('page:before', Badge('second'));

    renderPage('Dashboard', dashboard);

    await shows('page:before', 'firstsecond');
  });

  it('places header:end in the header, page:after in the content and footer:before first in the footer', () => {
    renderHook('header:end', Badge('in header'));
    renderHook('page:after', Badge('after page'));
    renderHook('footer:before', Badge('above footer'));

    renderPage('Dashboard', dashboard);

    expect(hook('header:end')?.closest('header')).not.toBeNull();
    expect(hook('page:after')?.closest('main')).not.toBeNull();
    expect(hook('page:after')?.previousElementSibling?.textContent).toContain('Dashboard');
    // First inside the footer landmark, so its content belongs to a region.
    expect(hook('footer:before')?.parentElement?.tagName).toBe('FOOTER');
    expect(hook('footer:before')?.previousElementSibling).toBeNull();
  });

  it('lets a head hook write into the document head with its own Head', async () => {
    renderHook('head', () => (
      <Head>
        <meta name="lathe-test" content="from a hook" />
      </Head>
    ));

    renderPage('Dashboard', dashboard);

    await waitFor(() => {
      expect(document.head.querySelector('meta[name="lathe-test"]')?.getAttribute('content')).toBe('from a hook');
    });
  });

  it('pins: renders nothing at a position without hooks', () => {
    renderPage('Dashboard', dashboard);

    expect(hook('sidebar:before')).toBeNull();
  });

  it('loads a React.lazy hook', async () => {
    renderHook('page:after', lazy(() => Promise.resolve({ default: Badge('loaded later') })));

    renderPage('Dashboard', dashboard);

    expect(await screen.findByText('loaded later')).toBeTruthy();
  });
});

describe('render hooks on resource pages', () => {
  it('passes the Resource to an index hook and keeps a scoped hook to its Resources', async () => {
    renderHook('index:header-actions', ({ resource }: RenderHookProps) => <span>{`for ${resource ?? 'none'}`}</span>);
    renderHook('index:header-actions', Badge('users only'), { resources: ['users'] });

    const { unmount } = renderIndex('orders');

    await shows('index:header-actions', 'for orders');
    unmount();

    renderIndex('users');

    await shows('index:header-actions', 'for usersusers only');
  });

  it('passes the Resource and the record to a detail hook', async () => {
    renderHook('detail:header-actions', ({ resource, record }: RenderHookProps) => <span>{`${resource ?? ''} #${String(record?.id)}`}</span>);

    renderDetail();

    await shows('detail:header-actions', 'orders #12');
    expect(hook('detail:header-actions')?.closest('header')).not.toBeNull();
  });

  it('renders auth:after-form under the sign-in form', async () => {
    renderHook('auth:after-form', Badge('single sign-on'));

    renderPage('Auth/Login', { username: 'email', errors: {} }, { shared: { auth: { user: null } }, url: '/lathe/login' });

    await shows('auth:after-form', 'single sign-on');
    expect(hook('auth:after-form')?.previousElementSibling?.querySelector('form')).not.toBeNull();
  });
});

describe('a hook that throws', () => {
  it('renders nothing, logs, and leaves the page and the other hooks working', async () => {
    const log = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    renderHook(
      'index:header-actions',
      () => {
        throw new Error('boom');
      },
      { resources: ['crashy'] },
    );
    renderHook('index:header-actions', Badge('still here'), { resources: ['crashy'] });

    renderIndex('crashy');

    await shows('index:header-actions', 'for crashystill here');
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Things');
    expect(log).toHaveBeenCalledWith('[lathe] a render hook at "index:header-actions" failed', expect.any(Error));
  });
});
