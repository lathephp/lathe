import { fireEvent, screen, within } from '@testing-library/react';
import { renderHook } from '@lathephp/core';
import { describe, expect, it } from 'vitest';
import type { SharedProps } from '../types';
import { renderPage } from './support';

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] };

// Groups and the optional keys go beyond NavigationSection, so the fixture is cast once here.
const navigation = [
  { label: 'Dashboards', icon: null, items: [{ label: 'Sales', href: '/lathe', icon: 'LayoutDashboard' }] },
  { label: 'Insights', icon: null, collapsible: true, items: [{ label: 'Overview', href: '/lathe/pages/overview', icon: null }] },
  {
    label: 'Archive',
    icon: null,
    collapsible: true,
    collapsedByDefault: true,
    items: [{ label: 'Old reports', href: '/lathe/pages/old', icon: null, active: false }],
  },
  {
    label: 'Shop',
    icon: null,
    collapsible: true,
    collapsedByDefault: true,
    items: [{ kind: 'group', label: 'Sales desk', icon: null, items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: 'ShoppingCart' }] }],
  },
  {
    label: 'Resources',
    icon: null,
    items: [
      { label: 'Customers', href: '/lathe/resources/customers', icon: null, badge: { value: 'New', type: 'success', tooltip: 'Added this week' } },
      { label: 'Pinned', href: '/lathe/pages/pinned', icon: null, active: true },
    ],
  },
] as unknown as SharedProps['navigation'];

function show(url: string, shared: Partial<SharedProps> = {}) {
  renderPage('Dashboard', dashboard, { url, shared: { navigation, ...shared } });

  return within(screen.getByRole('complementary'));
}

describe('sidebar sections', () => {
  it('folds a collapsible section with its chevron', () => {
    const nav = show('/lathe');
    const chevron = nav.getByRole('button', { name: 'Insights' });

    expect(chevron.getAttribute('aria-expanded')).toBe('true');
    expect(nav.getByRole('link', { name: 'Overview' })).toBeTruthy();

    fireEvent.click(chevron);

    expect(chevron.getAttribute('aria-expanded')).toBe('false');
    expect(nav.queryByRole('link', { name: 'Overview' })).toBeNull();
  });

  it('starts a collapsedByDefault section folded', () => {
    const nav = show('/lathe');

    expect(nav.getByRole('button', { name: 'Archive' }).getAttribute('aria-expanded')).toBe('false');
    expect(nav.queryByRole('link', { name: 'Old reports' })).toBeNull();
  });

  it('opens a collapsedByDefault section that holds the current page', () => {
    const nav = show('/lathe/resources/orders/12');

    expect(nav.getByRole('button', { name: 'Shop' }).getAttribute('aria-expanded')).toBe('true');
    expect(nav.getByText('Sales desk', { selector: 'h3' })).toBeTruthy();
    expect(nav.getByRole('link', { name: 'Orders' }).getAttribute('data-active')).toBe('true');
  });

  it('renders the sidebar render hooks around the navigation', async () => {
    renderHook('sidebar:before', () => <span>before the menu</span>);
    renderHook('sidebar:after', () => <span>after the menu</span>);
    show('/lathe');
    const nav = screen.getByRole('navigation', { name: 'Main' });

    await screen.findByText('before the menu');
    expect(nav.previousElementSibling?.getAttribute('data-lathe-hook')).toBe('sidebar:before');
    expect(nav.previousElementSibling?.textContent).toBe('before the menu');
    expect(nav.nextElementSibling?.getAttribute('data-lathe-hook')).toBe('sidebar:after');
    expect(nav.nextElementSibling?.textContent).toBe('after the menu');
  });
});

describe('sidebar active state', () => {
  it.each(['/lathe/resources/orders', '/lathe/resources/orders/12'])('marks Orders active on %s and not Customers or the home', (url) => {
    const nav = show(url);

    expect(nav.getByRole('link', { name: 'Orders' }).getAttribute('data-active')).toBe('true');
    expect(nav.getByRole('link', { name: /^Customers/ }).getAttribute('data-active')).toBe('false');
    expect(nav.getByRole('link', { name: 'Sales' }).getAttribute('data-active')).toBe('false');
  });

  it('takes the server override over the URL', () => {
    const nav = show('/lathe/pages/old');

    expect(nav.getByRole('link', { name: 'Pinned' }).getAttribute('data-active')).toBe('true');
    expect(nav.getByRole('button', { name: 'Archive' }).getAttribute('aria-expanded')).toBe('false');
  });
});

describe('sidebar badges and tooltips', () => {
  it('shows a lazy badge value with its type', () => {
    const nav = show('/lathe', { navigationBadges: { '/lathe/resources/orders': { value: '4', type: 'warning' } } });
    const badge = nav.getByText('4');

    expect(badge.dataset.badgeType).toBe('warning');
    expect(badge.className).toContain('lathe-nav-badge');
  });

  it('shows a badge tooltip on hover and on focus', () => {
    const nav = show('/lathe');
    const customers = nav.getByRole('link', { name: /^Customers/ });

    expect(nav.getByText('New').dataset.badgeType).toBe('success');
    expect(nav.queryByRole('tooltip')).toBeNull();

    fireEvent.mouseEnter(customers.parentElement as HTMLElement);

    expect(nav.getByRole('tooltip').textContent).toBe('Added this week');
    expect(customers.getAttribute('aria-describedby')).toBe(nav.getByRole('tooltip').id);

    fireEvent.mouseLeave(customers.parentElement as HTMLElement);
    fireEvent.focus(customers);

    expect(nav.getByRole('tooltip').textContent).toBe('Added this week');
  });

  it('names a rail icon in a tooltip on keyboard focus', () => {
    const nav = show('/lathe/resources/orders', { sidebarOpen: false });
    const orders = nav.getByRole('link', { name: 'Orders' });

    fireEvent.focus(orders);

    expect(nav.getByRole('tooltip').textContent).toBe('Orders');

    fireEvent.blur(orders);

    expect(nav.queryByRole('tooltip')).toBeNull();
  });
});
