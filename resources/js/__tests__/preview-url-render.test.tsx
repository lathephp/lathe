import { router } from '@inertiajs/react';
import { waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { orders, panel, previews } from './preview-support';
import { fakeInertiaServer, renderPage } from './support';

beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
  // A save to orders empties the panel's cache, which outlives a test.
  window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders' }));
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('the panel in the URL', () => {
  it('opens again on the linked order after a reload', async () => {
    previews();
    window.history.replaceState({}, '', '/lathe/resources/orders?preview=2');
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders?preview=2' });

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79186507');
  });
});
