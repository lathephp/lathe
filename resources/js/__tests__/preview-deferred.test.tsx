import { router } from '@inertiajs/react';
import { act, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstCell, orders, press, previews } from './preview-support';
import { fakeInertiaServer, renderPage } from './support';

// The page's first deferred load only runs on a router no earlier visit has touched, so this test has a file of its own.
beforeEach(() => {
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('the URL and the deferred props', () => {
  it('does not ask for the counts again when the panel puts its record in the URL while they load', async () => {
    const requests = fakeInertiaServer();
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders', page: { deferredProps: { counts: ['count'] } } });
    const counts = () => requests.filter((request) => request.headers['X-Inertia-Partial-Data'] === 'count');
    await waitFor(() => {
      expect(counts()).toHaveLength(1);
    });

    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
    });

    expect(counts()).toHaveLength(1);
  });
});
