import { router } from '@inertiajs/react';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, onceProps, renderPage } from './support';

// Its own file: Inertia's router keeps its page and its prefetches from one test to the next.

const navigation = [
  {
    label: 'Resources',
    icon: null,
    items: [{ label: 'Products', href: '/lathe/resources/products', icon: null, component: 'Resources/Index', cacheTag: 'resource:products', skeleton: { tabs: false, actions: false } }],
  },
];

const dashboard = (name: string) => ({
  dashboard: { uriKey: 'main', name, description: null, icon: null, showRefreshButton: false, ranges: [], range: null },
  widgets: [],
});

const productsPage = {
  resource: {
    uriKey: 'products',
    label: 'Products',
    singularLabel: 'Product',
    perPageOptions: [25],
    abilities: { create: false },
    definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
  },
  table: {
    columns: [{ key: 'name', label: 'Name', component: 'text' }],
    rows: [1, 2, 3].map((id) => ({ id, title: `Lamp ${String(id)}`, cells: { name: { value: `Lamp ${String(id)}`, display: `Lamp ${String(id)}` } }, abilities: {} })),
    rowKey: 'id',
  },
  state: { page: 1, perPage: 25, hasMorePages: false },
};

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('a click while the hover prefetch is still on its way', () => {
  it('keeps the shell, whose once props the prefetch answer leaves out', async () => {
    const requests = fakeInertiaServer();
    router.flushAll();
    vi.useFakeTimers();
    const once = onceProps('navigation', 'appName', 'basePath', 'layout', 'tableStyle', 'footer', 'versionLabel');
    renderPage('Dashboard', dashboard('Home'), { url: '/lathe', shared: { navigation }, page: { onceProps: once } });
    const products = within(screen.getByRole('complementary')).getByRole('link', { name: 'Products' });

    fireEvent.mouseEnter(products);
    await act(() => vi.advanceTimersByTimeAsync(80));
    expect(requests).toHaveLength(1);
    fireEvent.click(products);
    await act(() => vi.advanceTimersByTimeAsync(10));

    await act(async () => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: productsPage, url: '/lathe/resources/products', onceProps: once });
      await vi.advanceTimersByTimeAsync(10);
    });

    expect(screen.getByRole('complementary')).toBeTruthy();
    expect(screen.getByText('Lamp 3')).toBeTruthy();
  });
});
