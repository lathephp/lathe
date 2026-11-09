import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { orders, panel, previews } from './preview-support';
import { fakeInertiaServer, renderPage } from './support';

// The Orders list here edits Status inline, so its data-cell links keep the cursor first.
beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
});

afterEach(() => {
  vi.restoreAllMocks();
});

async function ready() {
  await waitFor(() => {
    expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
  });
}

describe('a click in the row-actions cell of a list that edits inline', () => {
  it('follows the view eye and the edit pencil from the first click', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    await ready();

    const view = screen.getByRole('link', { name: 'View ORD-79194426' });
    fireEvent.click(view, { detail: 1 });
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/1');

    const edit = screen.getByRole('link', { name: 'Edit ORD-79194426' });
    fireEvent.click(edit, { detail: 1 });
    expect(visit.mock.calls[1]?.[0]).toBe('/lathe/resources/orders/1/edit');
  });

  it('keeps a link in a data cell cursor-first, and follows it from the second click', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    await ready();
    const link = screen.getAllByRole('link', { name: 'Gerard Reichert' })[0] as HTMLElement;

    fireEvent.pointerDown(link);
    fireEvent.click(link, { detail: 1 });
    expect(visit).not.toHaveBeenCalled();
    expect(document.activeElement).toBe(link.closest('td'));

    fireEvent.pointerDown(link);
    fireEvent.click(link, { detail: 1 });
    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/customers/3');
  });

  it('opens the record page from one click on the eye while the preview panel is open', async () => {
    previews();
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage('Resources/Index', orders({ previewOpen: true }), { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    await ready();

    fireEvent.pointerDown(screen.getByRole('link', { name: 'View ORD-79194426' }));
    fireEvent.click(screen.getByRole('link', { name: 'View ORD-79194426' }), { detail: 1 });

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/1');
  });
});
