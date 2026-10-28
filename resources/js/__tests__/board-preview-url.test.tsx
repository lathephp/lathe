import { fireEvent, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { boardServer, orders } from './board-support';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('the Board and the preview URL', () => {
  it('writes the clicked card into ?preview=', async () => {
    boardServer();
    renderPage('Resources/Index', orders({ layout: 'board' }), { url: '/lathe/resources/orders?layout=board' });

    await waitFor(() => {
      expect(document.querySelector('[data-board-card="paid-1-3"]')).not.toBeNull();
    });
    fireEvent.click(document.querySelector('[data-board-card="paid-1-3"]') as HTMLElement);

    await waitFor(() => {
      expect(new URL(window.location.href).searchParams.get('preview')).toBe('paid-1-3');
    });
    expect(new URL(window.location.href).searchParams.get('layout')).toBe('board');
  });
});
