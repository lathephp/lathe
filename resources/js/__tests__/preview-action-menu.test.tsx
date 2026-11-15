import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
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
});

describe('the record action menu in the preview panel', () => {
  // At 1280 px the walk measured the panel at 856..1256 and the menu, ending at its button's end
  // edge, at 704..960: the panel scrolls, so a menu inside it lost its first 152 px. On the body,
  // no scrolling box cuts it.
  it('draws its menu on the body, outside the panel that scrolls', async () => {
    previews();
    renderPage('Resources/Index', orders({ previewOpen: true }), { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });

    fireEvent.click(await within(panel()).findByRole('button', { name: 'Actions' }));

    const menu = screen.getByRole('menu', { name: 'Actions' });
    expect(panel().contains(menu)).toBe(false);
    expect(menu.closest('#content')).toBeNull();
  });
});
