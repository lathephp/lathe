import { toast } from '@lathephp/core';
import { act, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Toaster } from '../components/Toaster';

// The Base UI chunk of an old build is gone after a deploy, so its import fails.
vi.mock('../components/ToastStack', () => {
  throw new Error('Failed to fetch dynamically imported module');
});

describe('the plain toasts', () => {
  it("draw the toast card with the style's toast class", async () => {
    render(<Toaster />);

    act(() => {
      toast.error('Could not save');
    });

    const card = await screen.findByRole('alert');

    expect(card.dataset.slot).toBe('toast');
    expect(card.classList).toContain('cn-toast');
    expect(card.className).not.toMatch(/text-destructive/);
    expect(card.closest('[data-slot="toast-viewport"]')?.classList).not.toContain('z-40');
  });
});
