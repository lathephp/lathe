import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel, toast } from '../index';

afterEach(() => {
  vi.useRealTimers();
  __panel.toasts.set([]);
});

describe('toast', () => {
  it('dismisses itself after a few seconds, an error a little later', () => {
    vi.useFakeTimers();
    toast.success('Saved');
    toast.error('Failed');

    vi.advanceTimersByTime(5_000);
    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['Failed']);

    vi.advanceTimersByTime(3_000);
    expect(__panel.toasts.get()).toEqual([]);
  });
});
