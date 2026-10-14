import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false }, widgets: [] };
const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

function impersonateCalls(fetch: { mock: { calls: unknown[][] } }) {
  return fetch.mock.calls.filter(([url]) => url === '/lathe/api/impersonate') as [string, RequestInit][];
}

function renderUsers(abilities: Record<string, boolean>) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'users',
        label: 'Users',
        singularLabel: 'User',
        perPageOptions: [25],
        searchable: false,
        softDeletes: false,
        abilities: { create: false },
        definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
      },
      table: {
        columns: [{ key: 'name', label: 'Name', component: 'text', sortable: false, sortKey: 'name' }],
        sortKeys: [],
        rows: [{ id: 2, title: 'Editor User', cells: { name: { value: 'Editor User', display: 'Editor User' } }, abilities }],
        rowKey: 'id',
      },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/users' },
  );
}

function openRowMenu(): string[] {
  fireEvent.click(screen.getByRole('button', { name: 'Actions for Editor User' }));

  return screen.getAllByRole('menuitem').map((item) => item.textContent);
}

describe('the impersonation banner', () => {
  it('names the impersonated user and stops with DELETE, then visits the redirect', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ redirect: '/lathe/resources/users' }));
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    renderPage('Dashboard', dashboard, { shared: { impersonating: { name: 'Editor User' } } });

    const banner = await screen.findByRole('status');
    expect(banner.textContent).toContain('You are currently impersonating Editor User.');

    fireEvent.click(screen.getByRole('button', { name: 'Stop Impersonating' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/users');
    });
    expect(impersonateCalls(fetch).map(([, init]) => init.method)).toEqual(['DELETE']);
  });

  it('is absent when no impersonation runs', async () => {
    renderPage('Dashboard', dashboard, { shared: { impersonating: null } });

    await screen.findByRole('heading', { level: 1 });
    expect(screen.queryByText(/You are currently impersonating/)).toBeNull();
    expect(screen.queryByRole('button', { name: 'Stop Impersonating' })).toBeNull();
  });
});

describe('the row menu Impersonate entry', () => {
  it('is offered only when the row may be impersonated', () => {
    const { unmount } = renderUsers({ view: true, impersonate: false });
    expect(openRowMenu()).toEqual(['View']);
    unmount();

    renderUsers({ view: true, impersonate: true });
    expect(openRowMenu()).toEqual(['View', 'Impersonate']);
  });

  it('posts the resource and row key, then visits the redirect', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ redirect: '/lathe' }));
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderUsers({ view: true, impersonate: true });

    openRowMenu();
    fireEvent.click(screen.getByRole('menuitem', { name: 'Impersonate' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe');
    });
    const [call] = impersonateCalls(fetch);
    expect(call?.[1].method).toBe('POST');
    expect(JSON.parse(call?.[1].body as string)).toEqual({ resource: 'users', resourceId: 2 });
  });
});
