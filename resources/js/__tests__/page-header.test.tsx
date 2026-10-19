import { registry } from '@lathephp/core';
import { screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

const navigation = [
  { label: null, icon: null, items: [{ label: 'Sales', href: '/lathe', icon: 'LayoutDashboard' }] },
  { label: 'Store', icon: null, items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: 'ShoppingCart' }] },
];

function renderOrders(url = '/lathe/resources/orders') {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25],
        searchable: true,
        abilities: { create: true },
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: null,
    },
    { url, shared: { navigation } },
  );
}

describe('top bar breadcrumb', () => {
  it('reads the menu group then the resource on an index page', () => {
    renderOrders();
    const crumbs = within(screen.getByRole('banner')).getByRole('navigation', { name: 'Breadcrumb' });

    expect(Array.from(crumbs.querySelectorAll('li')).map((item) => item.textContent)).toEqual(['Store', 'Orders']);
    expect(within(crumbs).getByText('Orders').getAttribute('aria-current')).toBe('page');
  });

  it('links the resource from a page below it', () => {
    renderOrders('/lathe/resources/orders/lenses/pending');
    const crumbs = within(screen.getByRole('banner')).getByRole('navigation', { name: 'Breadcrumb' });

    expect(within(crumbs).getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
  });

  it('moves a record page trail into the top bar', () => {
    renderPage(
      'Resources/Detail',
      {
        resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order' },
        record: { id: 12, title: 'ORD-1', subtitle: '', avatar: null, fields: [], layout: [], abilities: {}, trashed: false },
        actions: { actions: [] },
        widgets: [],
        breadcrumbs: [
          { label: 'Dashboard', href: '/lathe' },
          { label: 'Orders', href: '/lathe/resources/orders' },
          { label: 'ORD-1', href: null },
        ],
      },
      { url: '/lathe/resources/orders/12', shared: { navigation } },
    );
    const crumbs = within(screen.getByRole('banner')).getByRole('navigation', { name: 'Breadcrumb' });

    expect(Array.from(crumbs.querySelectorAll('li')).map((item) => item.textContent)).toEqual(['Dashboard', 'Orders', 'ORD-1']);
    expect(screen.getAllByRole('navigation', { name: 'Breadcrumb' })).toHaveLength(1);
  });
});

describe('page header', () => {
  it('puts the title and a sentence case Create button on one row above the table card', () => {
    renderOrders();
    const header = screen.getByRole('heading', { level: 1, name: 'Orders' }).closest('[data-lathe-header]') as HTMLElement;

    expect(within(header).getByRole('link', { name: 'Create order' }).getAttribute('href')).toBe('/lathe/resources/orders/new');
    expect(document.querySelector('[data-lathe-toolbar]')?.textContent).not.toContain('Create');
  });
});

describe('a replaced top bar', () => {
  it('hands the replacement the trail and keeps the title and Create in the page', () => {
    let trail: unknown;
    registry.replace('shell.header', (props: { slotVersion?: number; breadcrumbs?: unknown }) => {
      trail = props.breadcrumbs;

      return <header>Custom header</header>;
    });

    renderOrders();
    const header = document.querySelector<HTMLElement>('[data-lathe-header=""]');
    if (header === null) throw new Error('no page header');

    expect(trail).toEqual([
      { label: 'Store', href: null },
      { label: 'Orders', href: null },
    ]);
    // With no top bar to show them on a phone, the title stays visible and the icon-only Create sits in the page.
    expect(header.hasAttribute('data-page-bar')).toBe(false);
    expect(within(header).getAllByRole('link', { name: 'Create order' })).toHaveLength(2);
  });
});
