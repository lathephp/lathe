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

  it('keeps the oldest past 3 for the toaster to fold, and drops past 20', () => {
    toast.info('One');
    toast.info('Two');
    toast.warning('Three');
    toast.success('Four');

    expect(__panel.toasts.get().map((t) => t.message)).toEqual(['One', 'Two', 'Three', 'Four']);

    for (let n = 5; n <= 21; n++) {
      toast.info(`Toast ${String(n)}`);
    }

    expect(__panel.toasts.get()).toHaveLength(20);
    expect(__panel.toasts.get()[0]?.message).toBe('Two');
  });

  it('stays for the duration it is given', () => {
    toast.info('Exported', { duration: 10_000 });

    expect(__panel.toasts.get().map((t) => t.timeout)).toEqual([10_000]);
  });

  it('updates a toast in place by its id', () => {
    toast.info('Saved');
    const id = __panel.putToast({ type: 'info', message: 'Exporting 50 of 100', timeout: 0 });
    toast.info('Deleted');
    __panel.putToast({ type: 'success', message: 'Your export is ready for download.', body: 'Orders', timeout: 5_000 }, id);

    expect(__panel.toasts.get().map((t) => [t.message, t.body, t.timeout])).toEqual([
      ['Saved', undefined, 5_000],
      ['Your export is ready for download.', 'Orders', 5_000],
      ['Deleted', undefined, 5_000],
    ]);
    expect(__panel.toasts.get()[1]?.id).toBe(id);
  });
});
