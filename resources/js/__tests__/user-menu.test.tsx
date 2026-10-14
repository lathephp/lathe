import { router } from '@inertiajs/react';
import type { SafeHtml } from '@lathephp/core';
import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false }, widgets: [] };

afterEach(() => {
  vi.restoreAllMocks();
});

function openMenu() {
  fireEvent.click(within(screen.getByRole('complementary')).getByRole('button', { name: /Ada Lovelace/ }));

  return screen.getByRole('menu', { name: 'User menu' });
}

describe('user menu', () => {
  it('lists the identity, the items, Security, the appearance slot and Log out in that order', () => {
    renderPage('Dashboard', dashboard, {
      shared: {
        userMenu: [
          { label: 'Profile', href: '/lathe/profile', icon: null },
          { label: 'Security', href: '/lathe/security', icon: null },
        ],
      },
    });
    const menu = openMenu();

    expect(within(menu).getByRole('menuitem', { name: 'Profile' }).getAttribute('href')).toBe('/lathe/profile');
    expect(within(menu).getByRole('menuitem', { name: 'Security' }).getAttribute('href')).toBe('/lathe/security');
    expect(
      Array.from(menu.querySelectorAll('[role="menuitem"], [data-lathe-appearance-slot]')).map((node) =>
        node.hasAttribute('data-lathe-appearance-slot') ? 'slot' : node.textContent,
      ),
    ).toEqual(['Profile', 'Security', 'slot', 'Log out']);
    expect(menu.textContent.startsWith('Ada Lovelaceada@example.com')).toBe(true);
  });

  it('logs out with a POST to the panel path', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    renderPage('Dashboard', dashboard);

    fireEvent.click(within(openMenu()).getByRole('menuitem', { name: 'Log out' }));

    expect(post).toHaveBeenCalledWith('/lathe/logout');
  });
});

describe('footer html', () => {
  it('renders the sanitized HTML of a custom footer', () => {
    const html = { __html: '<a href="/x">Terms</a>' } as SafeHtml;
    renderPage('Dashboard', dashboard, { shared: { footer: { html } } });

    expect(within(screen.getByRole('contentinfo')).getByRole('link', { name: 'Terms' }).getAttribute('href')).toBe('/x');
  });
});

describe('breadcrumbs', () => {
  const detail = {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order' },
    record: { id: 12, title: 'ORD-1', subtitle: '', avatar: null, fields: [], layout: [], abilities: {}, trashed: false },
    actions: { actions: [] },
    widgets: [],
  };

  it('renders no navigation for an empty trail', () => {
    renderPage('Resources/Detail', { ...detail, breadcrumbs: [] });

    expect(screen.queryByRole('navigation', { name: 'Breadcrumb' })).toBeNull();
  });

  it('pins the last crumb as plain text marked as the current page', () => {
    renderPage('Resources/Detail', { ...detail, breadcrumbs: [{ label: 'Orders', href: '/lathe/resources/orders' }, { label: '#12', href: null }] });
    const last = screen.getByText('#12');

    expect(last.tagName).toBe('SPAN');
    expect(last.getAttribute('aria-current')).toBe('page');
  });
});
