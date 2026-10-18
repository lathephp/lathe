import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const dashboard = (name: string) => ({
  dashboard: { uriKey: 'main', name, description: null, icon: null, showRefreshButton: false },
  widgets: [],
});

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('a visit through the fake server', () => {
  it('sends a clicked sidebar link as an Inertia request and renders the page it answers', async () => {
    renderPage('Dashboard', dashboard('Home'), { url: '/lathe' });

    fireEvent.click(within(screen.getByRole('complementary')).getByRole('link', { name: 'Orders' }));

    await vi.waitFor(() => {
      expect(requests).toHaveLength(1);
    });
    expect(requests[0]?.url).toContain('/lathe/resources/orders');
    expect(requests[0]?.headers['X-Inertia']).toBe('true');

    await act(async () => {
      requests[0]?.respond(200, { component: 'Dashboard', props: dashboard('Sales'), url: '/lathe/resources/orders' });
      await Promise.resolve();
    });

    expect(await screen.findByRole('heading', { name: 'Sales' })).toBeTruthy();
  });
});

const navigation = [
  { label: null, icon: null, items: [{ label: 'Dashboard', href: '/lathe', icon: null, component: 'Dashboard' }] },
  {
    label: 'Resources',
    icon: null,
    items: [
      { label: 'Orders', href: '/lathe/resources/orders', icon: null, component: 'Resources/Index', cacheTag: 'resource:orders' },
      { label: 'Products', href: '/lathe/resources/products', icon: null, component: 'Resources/Index', cacheTag: 'resource:products' },
    ],
  },
];

function sidebar() {
  renderPage('Dashboard', dashboard('Home'), { url: '/lathe', shared: { navigation } });

  return within(screen.getByRole('complementary'));
}

describe('a sidebar link', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('turns active on pointerdown, before the server answers', () => {
    const nav = sidebar();

    fireEvent.pointerDown(nav.getByRole('link', { name: 'Products' }));

    expect(nav.getByRole('link', { name: 'Products' }).getAttribute('aria-current')).toBe('page');
    expect(nav.getByRole('link', { name: 'Dashboard' }).getAttribute('aria-current')).toBeNull();
    expect(requests).toHaveLength(0);
  });

  it('gives the active state back when the pointer leaves without a click', () => {
    const nav = sidebar();
    const products = nav.getByRole('link', { name: 'Products' });

    fireEvent.pointerDown(products);
    fireEvent.pointerLeave(products, { buttons: 1 });

    expect(products.getAttribute('aria-current')).toBeNull();
    expect(nav.getByRole('link', { name: 'Dashboard' }).getAttribute('aria-current')).toBe('page');
  });

  it('prefetches its page after a 75 ms hover, and not after 50 ms', async () => {
    vi.useFakeTimers();
    const orders = sidebar().getByRole('link', { name: 'Orders' });

    fireEvent.mouseEnter(orders);
    await act(() => vi.advanceTimersByTimeAsync(50));
    fireEvent.mouseLeave(orders);
    await act(() => vi.advanceTimersByTimeAsync(200));
    expect(requests).toHaveLength(0);

    fireEvent.mouseEnter(orders);
    await act(() => vi.advanceTimersByTimeAsync(75));
    expect(requests).toHaveLength(1);
    expect(requests[0]?.headers.Purpose).toBe('prefetch');
  });

  it('opens a prefetched page with no new request, and its cache tag flushes it', async () => {
    vi.useFakeTimers();
    const orders = sidebar().getByRole('link', { name: 'Orders' });

    fireEvent.mouseEnter(orders);
    await act(() => vi.advanceTimersByTimeAsync(75));
    await act(async () => {
      requests[0]?.respond(200, { component: 'Dashboard', props: dashboard('Orders page'), url: '/lathe/resources/orders' });
      await vi.advanceTimersByTimeAsync(10);
    });
    expect(router.getCached('/lathe/resources/orders')).toBeTruthy();

    fireEvent.click(orders);
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10);
    });
    expect(requests).toHaveLength(1);
    expect(screen.getByRole('heading', { name: 'Orders page' })).toBeTruthy();

    router.flushByCacheTags('resource:orders');

    expect(router.getCached('/lathe/resources/orders')).toBeNull();
  });
});

describe('a hover or a step back', () => {
  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows no toast and no error page when the hovered page is forbidden', async () => {
    vi.useFakeTimers();
    const orders = sidebar().getByRole('link', { name: 'Orders' });

    fireEvent.mouseEnter(orders);
    await act(() => vi.advanceTimersByTimeAsync(75));
    await act(async () => {
      requests[0]?.respond(403, { component: 'Error', props: { status: 403, shell: true }, url: '/lathe/resources/orders' });
      await vi.advanceTimersByTimeAsync(10);
    });

    expect(screen.getByRole('heading', { name: 'Home' })).toBeTruthy();
    expect(__panel.toasts.get()).toEqual([]);
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});
