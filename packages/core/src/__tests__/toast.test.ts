import { afterEach, describe, expect, it } from 'vitest';
import { __panel, toast } from '../index';

afterEach(() => {
  __panel.toasts.set([]);
});

describe('toast', () => {
  it('gives a toast 5 seconds and keeps an error until it is dismissed', () => {
    toast.success('Saved');
    toast.error('Failed');

    expect(__panel.toasts.get().map((t) => [t.message, t.timeout])).toEqual([
      ['Saved', 5_000],
      ['Failed', 0],
    ]);
  });

  it('shows at most 3 at once, dropping the oldest', () => {
    toast.info('One');
    toast.info('Two');
    toast.warning('Three');
    toast.success('Four');

    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['Two', 'Three', 'Four']);
  });

  it('stays for the duration it is given', () => {
    toast.info('Exported', { duration: 10_000 });

    expect(__panel.toasts.get().map((t) => t.timeout)).toEqual([10_000]);
  });
});
