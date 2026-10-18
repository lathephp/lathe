import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const navigation = [
  { label: null, icon: null, items: [{ label: 'Dashboard', href: '/lathe', icon: null, component: 'Dashboard' }] },
  {
    label: 'Resources',
    icon: null,
    items: [
      {
        label: 'Orders',
        href: '/lathe/resources/orders',
        icon: null,
        component: 'Resources/Index',
        cacheTag: 'resource:orders',
        skeleton: { tabs: true, actions: true },
      },
      {
        label: 'Products',
        href: '/lathe/resources/products',
        icon: null,
        component: 'Resources/Index',
        cacheTag: 'resource:products',
        skeleton: { tabs: false, actions: true },
      },
      { label: 'Reports', href: '/lathe/dashboards/reports', icon: null, component: 'Dashboard' },
    ],
  },
];

const dashboard = (name: string) => ({
  dashboard: { uriKey: 'main', name, description: null, icon: null, showRefreshButton: false },
  widgets: [],
});

const productsPage = {
  resource: {
    uriKey: 'products',
    label: 'Products',
    singularLabel: 'Product',
    perPageOptions: [25],
    abilities: { create: false },
    definition: {
      debounce: 0.5,
      paginationType: 'links',
      clickAction: 'detail',
      polling: null,
      showPollingToggle: false,
      tableStyle: 'default',
      showColumnBorders: false,
      softDeletes: false,
    },
  },
  table: {
    columns: [{ key: 'name', label: 'Name', component: 'text' }],
    rows: [1, 2, 3].map((id) => ({ id, title: `Lamp ${String(id)}`, cells: { name: { value: `Lamp ${String(id)}`, display: `Lamp ${String(id)}` } }, abilities: {} })),
    rowKey: 'id',
  },
  state: { page: 1, perPage: 25, hasMorePages: false },
};

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  vi.useFakeTimers();
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

function clickInSidebar(label: string) {
  renderPage('Dashboard', dashboard('Home'), { url: '/lathe', shared: { navigation } });
  fireEvent.click(within(screen.getByRole('complementary')).getByRole('link', { name: label }));
}

const skeletonRows = () => document.querySelectorAll('[data-skeleton-row]');

describe('an instant visit from the sidebar', () => {
  it('shows the index title and ten skeleton rows after 100 ms, and nothing of it before', async () => {
    clickInSidebar('Products');

    await act(() => vi.advanceTimersByTimeAsync(90));
    expect(screen.queryByRole('heading', { name: 'Home' })).toBeNull();
    expect(screen.queryByRole('heading', { name: 'Products' })).toBeNull();
    expect(skeletonRows()).toHaveLength(0);

    await act(() => vi.advanceTimersByTimeAsync(20));
    expect(screen.getByRole('heading', { name: 'Products' })).toBeTruthy();
    expect(skeletonRows()).toHaveLength(10);
    expect(requests).toHaveLength(1);
  });

  it('draws the tab band and the action bar only for an index that has them', async () => {
    clickInSidebar('Orders');
    await act(() => vi.advanceTimersByTimeAsync(110));
    expect(document.querySelectorAll('[data-skeleton-tabs]')).toHaveLength(1);
    expect(document.querySelectorAll('[data-skeleton-actions]')).toHaveLength(1);
    // As tall as the action buttons in whichever kit style the panel uses.
    expect(document.querySelector('[data-skeleton-actions] > div')?.className).toContain('cn-button-size-sm');
  });

  it('lays its header out as the page does: the title on a row, the controls below', async () => {
    clickInSidebar('Products');
    await act(() => vi.advanceTimersByTimeAsync(110));
    const rows = document.querySelectorAll('[data-lathe-toolbar] > div');

    // A column, so the controls never share the title's row, however many there are.
    expect(document.querySelector('[data-lathe-toolbar]')?.className).toContain('flex-col');

    expect(rows).toHaveLength(2);
    expect(rows[0]?.querySelector('h1')?.textContent).toBe('Products');
  });

  it('leaves out the tab band of an index without tabs', async () => {
    clickInSidebar('Products');
    await act(() => vi.advanceTimersByTimeAsync(110));
    expect(document.querySelectorAll('[data-skeleton-tabs]')).toHaveLength(0);
    expect(document.querySelectorAll('[data-skeleton-actions]')).toHaveLength(1);
  });

  it('puts the rows where the skeleton was when the page arrives', async () => {
    clickInSidebar('Products');
    await act(() => vi.advanceTimersByTimeAsync(150));

    await act(async () => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: productsPage, url: '/lathe/resources/products' });
      await vi.advanceTimersByTimeAsync(10);
    });

    expect(skeletonRows()).toHaveLength(0);
    expect(screen.getByText('Lamp 3')).toBeTruthy();
    expect(screen.getAllByRole('heading', { name: 'Products' })).toHaveLength(1);
  });

  it('shows a dashboard title and widget frames before its widgets are known', async () => {
    clickInSidebar('Reports');
    await act(() => vi.advanceTimersByTimeAsync(110));

    expect(screen.getByRole('heading', { name: 'Reports' })).toBeTruthy();
    expect(document.querySelectorAll('[data-skeleton-frame]').length).toBeGreaterThan(0);
  });
});

describe('a record page without its record', () => {
  it('renders its title and three section cards, without an error', async () => {
    renderPage('Resources/Detail', { title: 'ORD-00012' }, { url: '/lathe/resources/orders/12' });
    await act(() => vi.advanceTimersByTimeAsync(110));

    expect(screen.getByRole('heading', { name: 'ORD-00012' })).toBeTruthy();
    expect(document.querySelectorAll('[data-skeleton-card]')).toHaveLength(3);
  });
});
