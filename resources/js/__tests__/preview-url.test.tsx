import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstCell, orders, panel, press, previews, reply, urlOf } from './preview-support';
import { fakeInertiaServer, renderPage } from './support';

// Inertia's router and jsdom's history outlive a test, so the URL's tests have a file of their own.
beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
  // A save to orders empties the panel's cache, which outlives a test.
  window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders' }));
});

afterEach(() => {
  vi.restoreAllMocks();
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

  it('opens a linked order that is not on the page from its own reply, with no cell cursor placed', async () => {
    const asked: string[] = [];
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);
      asked.push(url);

      return Promise.resolve(new Response(JSON.stringify({ ...reply(1), id: 99, title: 'ORD-00000099' }), { status: 200 }));
    });
    window.history.replaceState({}, '', '/lathe/resources/orders?preview=99');
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders?preview=99' });

    await waitFor(() => {
      expect(within(panel()).getByRole('heading').textContent).toBe('ORD-00000099');
    });
    expect(asked).toEqual(['/lathe/api/resources/orders/99/preview']);
    expect(screen.getByRole('grid').contains(document.activeElement)).toBe(false);
    expect(new URLSearchParams(window.location.search).get('preview')).toBe('99');
  });

  it('keeps the history length while the panel follows the cursor, so one Back leaves the list', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const entries = window.history.length;

    firstCell().focus();
    await press(' ');
    for (const expected of ['2', '3']) {
      fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowDown' });
      await waitFor(() => {
        expect(new URLSearchParams(window.location.search).get('preview')).toBe(expected);
      });
    }

    expect(window.history.length).toBe(entries);
  });

  it('puts the record back in the URL after a visit that rebuilt it, such as a sort', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });

    act(() => {
      router.replace({ url: '/lathe/resources/orders?sort=number', preserveState: true });
    });

    await waitFor(() => {
      expect(window.location.search).toBe('?sort=number&preview=1');
    });
    expect(panel()).not.toBeNull();
  });
});
