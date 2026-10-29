import { page } from '@lathephp/core';
import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

function Gallery({ pageKey, basePath, path }: { pageKey: string; basePath: string; path?: string }) {
  return <p>{`${pageKey} at ${basePath} on ${path ?? ''}`}</p>;
}

function renderStandalone(key: string, path = '') {
  return renderPage('Page', { pageKey: key, pagePath: `/lathe/pages/${key}`, path, title: 'UI Gallery' }, { url: `/lathe/pages/${key}` });
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('standalone page', () => {
  it('renders the registered component in the shell with its key, base path and sub path', () => {
    page('ui-gallery', Gallery);
    renderStandalone('ui-gallery', 'a/b');

    expect(within(screen.getByRole('main')).getByText('ui-gallery at /lathe/pages/ui-gallery on a/b')).toBeTruthy();
  });

  it('shows an in-shell notice and logs the key when no module registered the page', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    renderStandalone('missing-page');

    expect(within(screen.getByRole('main')).getByText('This page is not available.')).toBeTruthy();
    expect(error).toHaveBeenCalledWith('[lathe] no component is registered for the page "missing-page"');
  });

  it("ends the breadcrumb with its own name and leaves the dashboard's sidebar item inactive", () => {
    page('system-status', Gallery);
    renderPage(
      'Page',
      { pageKey: 'system-status', pagePath: '/lathe/pages/system-status', path: '', title: 'System Status' },
      {
        url: '/lathe/pages/system-status',
        shared: {
          navigation: [
            { label: null, icon: null, items: [{ label: 'Sales', href: '/lathe', icon: 'LayoutDashboard' }] },
            { label: 'Demo Kit', icon: null, items: [{ label: 'System Status', href: '/lathe/pages/system-status', icon: 'Activity' }] },
          ],
        },
      },
    );

    const crumbs = screen.getByRole('navigation', { name: 'Breadcrumb' });
    expect(within(crumbs).queryByText('Sales')).toBeNull();
    expect(within(crumbs).getByText('System Status').getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('link', { name: 'Sales' }).getAttribute('aria-current')).toBeNull();
  });
});
