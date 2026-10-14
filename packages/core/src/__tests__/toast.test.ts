import { afterEach, describe, expect, it, vi } from 'vitest';
import { __panel, toast } from '../index';

afterEach(() => {
  vi.useRealTimers();
  __panel.toasts.set([]);
});

describe('toast', () => {
  it('dismisses itself after 4 seconds, an error too', () => {
    vi.useFakeTimers();
    toast.success('Saved');
    toast.error('Failed');

    vi.advanceTimersByTime(3_999);
    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['Saved', 'Failed']);

    vi.advanceTimersByTime(1);
    expect(__panel.toasts.get()).toEqual([]);
  });

  it('shows at most 3 at once, dropping the oldest', () => {
    toast.info('One');
    toast.info('Two');
    toast.warning('Three');
    toast.success('Four');

    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['Two', 'Three', 'Four']);
  });

  it('stays for the duration it is given', () => {
    vi.useFakeTimers();
    toast.info('Exported', { duration: 10_000 });

    vi.advanceTimersByTime(9_999);
    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['Exported']);

    vi.advanceTimersByTime(1);
    expect(__panel.toasts.get()).toEqual([]);
  });
});
