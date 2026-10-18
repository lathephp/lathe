import { router } from '@inertiajs/react';
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
