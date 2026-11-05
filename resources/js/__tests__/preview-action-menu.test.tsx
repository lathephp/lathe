import { router } from '@inertiajs/react';
import { fireEvent, waitFor, within } from '@testing-library/react';
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
  // edge, at 704..960: inside the content, but the panel scrolls, so it cut the menu's first 152 px.
  it('opens from its button\'s start edge when, ending at the button\'s end edge, it would run out of the panel', async () => {
    previews();
    renderPage('Resources/Index', orders({ previewOpen: true }), { url: '/lathe/resources/orders' });
    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    // jsdom loads no stylesheet, so the panel scrolls as its overflow-y-auto class makes it.
    panel().style.overflowY = 'auto';
    vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (this: HTMLElement) {
      const [left, right] = this.getAttribute('role') === 'menu' ? [704, 960] : this.hasAttribute('data-lathe-preview') ? [856, 1256] : this.id === 'content' ? [256, 1280] : [0, 0];

      return { left, right, top: 0, bottom: 0, x: left, y: 0, width: right - left, height: 0, toJSON: () => ({}) };
    });

    fireEvent.click(await within(panel()).findByRole('button', { name: 'Actions' }));

    expect(within(panel()).getByRole('menu', { name: 'Actions' }).className).toMatch(/(?:^|\s)start-0(?:\s|$)/);
  });
});
