import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstCell, orders, panel, press, previews, reply, urlOf } from './preview-support';
import { cellOf, editCell, fakeInertiaServer, pick, renderPage } from './support';

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
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('the panel in the URL', () => {
  it('keeps a row saved before the panel opened new, in the grid and the header, once the URL takes the record', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);
      const match = /\/orders\/(\d+)\/preview$/.exec(url);

      if (url.endsWith('/field/status')) {
        return Promise.resolve(new Response(JSON.stringify({ fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 't1' }), { status: 200 }));
      }

      return Promise.resolve(match === null ? new Response(null, { status: 204 }) : new Response(JSON.stringify(reply(Number(match[1]), 'Paid')), { status: 200 }));
    });
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    await editCell('Status', 'Enter', 0);
    await pick('Paid');
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });
    await press(' ');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
    });

    expect(cellOf('Status').textContent).toContain('Paid');
    expect(panel().querySelector('header')?.textContent).toContain('Paid');
  });

  it('opens a linked order that is not on the page from its own reply, with no cell cursor placed', async () => {
    const asked: string[] = [];
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);
      asked.push(url);

      const status = { key: 'status', label: 'Status', component: 'select-field', value: 'paid', display: 'Paid', options: [{ value: 'paid', label: 'Paid' }], badges: { paid: 'info' } };

      return Promise.resolve(new Response(JSON.stringify({ ...reply(1), id: 99, title: 'ORD-00000099', fields: [...reply(1).fields, status] }), { status: 200 }));
    });
    window.history.replaceState({}, '', '/lathe/resources/orders?preview=99');
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders?preview=99' });

    await waitFor(() => {
      expect(within(panel()).getByRole('heading').textContent).toBe('ORD-00000099');
    });
    // With no row on the page, the card's badge comes from the record's own field.
    expect(panel().querySelector('header')?.textContent).toContain('Paid');
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

describe('the panel on a phone', () => {
  it('opens full screen from a card tap with Close at its top, and Close hands the focus back to that card without scrolling', async () => {
    const asked = previews();
    vi.stubGlobal('matchMedia', (query: string) => ({ matches: query === '(width < 48rem)' }));
    const scroll = vi.spyOn(window, 'scrollTo').mockImplementation(() => undefined);
    renderPage('Resources/Index', orders({ previewOpen: true }), { url: '/lathe/resources/orders' });

    // The saved open state does not cover a phone's list by itself.
    expect(panel()).toBeNull();

    const card = document.querySelector<HTMLElement>('[data-lathe-cards] [data-id="2"]') as HTMLElement;
    fireEvent.click(card);

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    expect(within(panel().querySelector('header') as HTMLElement).getByRole('button', { name: 'Close' })).toBeTruthy();
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79186507');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('2');
    });
    expect(asked).toEqual(['/lathe/api/resources/orders/2/preview']);

    const focus = vi.spyOn(card, 'focus');
    fireEvent.click(within(panel()).getByRole('button', { name: 'Close' }));

    expect(panel()).toBeNull();
    expect(focus).toHaveBeenCalledWith({ preventScroll: true });
    expect(document.activeElement).toBe(card);
    expect(scroll).not.toHaveBeenCalled();
  });
});
