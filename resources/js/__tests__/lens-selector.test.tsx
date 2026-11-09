import { router } from '@inertiajs/react';
import { fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const pending = { uriKey: 'pending-orders', name: 'Pending Orders' };

const tabs = {
  items: ['All', 'Pending', 'Paid'].map((name) => ({ key: name.toLowerCase(), name, icon: null, badge: null, badgeType: null, deferredBadge: false })),
  active: null,
  saved: [{ id: 7, name: 'Big paid', badgeType: null, showCount: false, stale: false, filters: { status: 'paid' }, q: null, sort: null }],
  activeSaved: null,
};

function renderOrders(lenses: { uriKey: string; name: string }[], lens: { uriKey: string; name: string } | null = null, extra: Record<string, unknown> = {}) {
  const props = {
    ...extra,
    resource: {
      uriKey: 'orders',
      label: 'Orders',
      singularLabel: 'Order',
      perPageOptions: [25],
      searchable: false,
      abilities: { create: false },
      definition: { debounce: 0.5 },
    },
    table: { columns: [], sortKeys: [], rows: [{ id: 1, title: 'ORD-1', cells: {}, abilities: { view: true } }], rowKey: 'id' },
    state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
    count: null,
    lens,
    lenses,
  };
  const url = lens === null ? '/lathe/resources/orders' : `/lathe/resources/orders/lens/${lens.uriKey}`;

  return renderPage('Resources/Index', props, { url });
}

describe('lens tabs', () => {
  it('puts each lens in the views rail as a link, none current on the Resource itself', () => {
    renderOrders([pending]);

    const band = screen.getByRole('navigation', { name: 'Views' });
    const link = within(band).getByRole('link', { name: 'Pending Orders' });

    expect(link.getAttribute('href')).toBe('/lathe/resources/orders/lens/pending-orders');
    expect(link.getAttribute('aria-current')).toBeNull();
  });

  it('marks the open lens as current and heads the page with its name and a back link', () => {
    renderOrders([pending], pending);

    expect(within(screen.getByRole('navigation', { name: 'Views' })).getByRole('link', { name: 'Pending Orders' }).getAttribute('aria-current')).toBe('page');
    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Pending Orders');
    const crumbs = within(screen.getByRole('banner')).getByRole('navigation', { name: 'Breadcrumb' });

    expect(within(crumbs).getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
  });

  it('draws no lens tab and no view switcher when the Resource has no lenses', () => {
    renderOrders([]);

    expect(screen.queryByRole('navigation', { name: 'Views' })).toBeNull();
    expect(screen.queryByRole('button', { name: /^Views/ })).toBeNull();
    expect(screen.queryByRole('combobox', { name: 'View' })).toBeNull();
  });

  it("lists the Resource's tabs, then the saved views, then the lenses on a lens, with only the lens current", () => {
    renderOrders([pending], pending, { tabs });

    const band = screen.getByRole('navigation', { name: 'Views' });

    expect(within(band).getAllByRole('listitem').map((item) => item.textContent.trim())).toEqual(['All', 'Pending', 'Paid', 'Big paid', 'Pending Orders']);
    expect(within(band).queryAllByRole('button', { current: 'page' })).toEqual([]);
    expect(within(band).getByRole('link', { name: 'Pending Orders' }).getAttribute('aria-current')).toBe('page');
  });

  it("opens the Resource's list from a lens on a tab or a saved view", () => {
    const get = vi.spyOn(router, 'get').mockImplementation(() => undefined);
    renderOrders([pending], pending, { tabs });
    const band = screen.getByRole('navigation', { name: 'Views' });

    fireEvent.click(within(band).getByRole('button', { name: 'Paid' }));
    fireEvent.click(within(band).getByRole('button', { name: 'Big paid' }));

    expect(get.mock.calls.map(([url, data]) => [url, data])).toEqual([
      ['/lathe/resources/orders', { tab: 'paid' }],
      ['/lathe/resources/orders', { saved_tab: '7' }],
    ]);
  });
});
