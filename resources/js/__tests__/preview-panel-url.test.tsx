import { router } from '@inertiajs/react';
import { act, fireEvent, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstCell, orders, panel, press, previews, reply, urlOf } from './preview-support';
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
  vi.useRealTimers();
});

describe('the preview panel', () => {
  it('opens on the cursor row with Space, heads it with the row card at once and puts the id in the URL without a history entry', async () => {
    let answer: ((response: Response) => void) | null = null;
    vi.spyOn(globalThis, 'fetch').mockImplementation(
      (input) =>
        new Promise((resolve) => {
          const url = urlOf(input);

          if (url.endsWith('/preview')) {
            answer = resolve;
          } else {
            resolve(new Response(null, { status: 204 }));
          }
        }),
    );
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const entries = window.history.length;

    firstCell().focus();
    await press(' ');

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    // The header comes from the row, before the preview answers.
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79194426');
    // The badge is the row's own Status cell.
    expect(panel().querySelector('header')?.textContent).toContain('Pending');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });
    expect(window.history.length).toBe(entries);

    await waitFor(() => {
      expect(answer).not.toBeNull();
    });
    act(() => {
      answer?.(new Response(JSON.stringify(reply(1)), { status: 200 }));
    });
    expect(await within(panel()).findByText('Oct 18, 2026')).toBeTruthy();
  });

  it('closes with Space on the same row, and with Close, which hands the focus back to the cursor', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    firstCell().focus();
    await press(' ');
    await waitFor(() => panel());
    await press(' ');
    expect(panel()).toBeNull();

    await press(' ');
    const aside = await waitFor(() => panel());
    fireEvent.click(within(aside).getByRole('button', { name: 'Close' }));

    expect(panel()).toBeNull();
    expect(document.activeElement).toBe(firstCell());
    await waitFor(() => {
      expect(window.location.search).toBe('');
    });
  });
});
