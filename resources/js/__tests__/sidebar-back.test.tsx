import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage } from './support';

// Its own file: Inertia's router and the browser history outlive a test.

const dashboard = (name: string) => ({
  dashboard: { uriKey: 'main', name, description: null, icon: null, showRefreshButton: false },
  widgets: [],
});

const navigation = [
  { label: null, icon: null, items: [{ label: 'Dashboard', href: '/lathe', icon: null, component: 'Dashboard' }] },
  { label: 'Resources', icon: null, items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: null, component: 'Resources/Index', cacheTag: 'resource:orders' }] },
];

afterEach(() => {
  vi.unstubAllGlobals();
});

it('marks the page it went Back to as current, not the link pressed before', async () => {
  const requests = fakeInertiaServer();
  renderPage('Dashboard', dashboard('Home'), { url: '/lathe', shared: { navigation } });
  const nav = within(screen.getByRole('complementary'));

  fireEvent.pointerDown(nav.getByRole('link', { name: 'Orders' }), { button: 0 });
  fireEvent.click(nav.getByRole('link', { name: 'Orders' }));
  await vi.waitFor(() => {
    expect(requests).toHaveLength(1);
  });
  await act(async () => {
    requests[0]?.respond(200, { component: 'Dashboard', props: dashboard('Orders page'), url: '/lathe/resources/orders' });
    await Promise.resolve();
  });
  expect(await screen.findByRole('heading', { name: 'Orders page' })).toBeTruthy();

  await act(async () => {
    window.history.back();
    await new Promise((resolve) => setTimeout(resolve, 100));
  });

  expect(screen.getByRole('heading', { name: 'Home' })).toBeTruthy();
  expect(nav.getByRole('link', { name: 'Dashboard' }).getAttribute('aria-current')).toBe('page');
  expect(nav.getByRole('link', { name: 'Orders' }).getAttribute('aria-current')).toBeNull();
});
