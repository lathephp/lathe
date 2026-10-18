import { act, fireEvent, screen, within } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage } from './support';

// Its own file: Inertia's router and the browser history outlive a test, so a visit made by an
// earlier test in the same file would sit in the history this one steps back through.

const dashboard = (name: string) => ({
  dashboard: { uriKey: 'main', name, description: null, icon: null, showRefreshButton: false, ranges: [], range: null },
  widgets: [],
});

const navigation = [
  { label: 'Resources', icon: null, items: [{ label: 'Orders', href: '/lathe/resources/orders', icon: null, component: 'Resources/Index', cacheTag: 'resource:orders' }] },
];

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

it('goes Back from an instant visit to the previous page and its scroll, without asking the server again', async () => {
  const requests = fakeInertiaServer();
  const scrollTo = vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
  renderPage('Dashboard', dashboard('Home'), { url: '/lathe', shared: { navigation } });

  // The person has read down the page before leaving it.
  vi.spyOn(window, 'scrollY', 'get').mockReturnValue(640);
  window.dispatchEvent(new Event('scroll'));
  await act(() => new Promise((resolve) => setTimeout(resolve, 200)));

  fireEvent.click(within(screen.getByRole('complementary')).getByRole('link', { name: 'Orders' }));
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

  expect(window.location.pathname).toBe('/lathe');
  expect(screen.getByRole('heading', { name: 'Home' })).toBeTruthy();
  expect(scrollTo).toHaveBeenLastCalledWith(0, 640);
  expect(requests).toHaveLength(1);
});
