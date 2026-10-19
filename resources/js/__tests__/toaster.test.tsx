import { __panel, toast } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Toaster } from '../components/Toaster';

afterEach(() => {
  vi.useRealTimers();
});

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('Toaster', () => {
  it('shows a success toast as a status', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    expect((await screen.findByRole('status')).textContent).toContain('Saved');
  });

  it('marks each toast with its kind on a solid dot and a running task with a spinner', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
      toast.error('Not saved');
    });

    const saved = (await screen.findByText('Saved')).closest('[data-slot="toast"]') as HTMLElement;
    const failed = screen.getByRole('alert');
    expect(saved.querySelector('[data-toast-icon]')?.className).toContain('bg-success text-success-foreground');
    expect(failed.querySelector('[data-toast-icon]')?.className).toContain('bg-destructive text-destructive-foreground');
    // The kind shows in the dot, so an error's text keeps the foreground colour.
    expect(failed.className).not.toContain('text-destructive');

    act(() => {
      __panel.putToast({ type: 'info', message: 'Marking 3 orders shipped', timeout: 0, dismissible: false });
    });
    const running = (await screen.findByText('Marking 3 orders shipped')).closest('[data-slot="toast"]') as HTMLElement;
    expect(running.querySelector('[data-toast-icon]')?.className).toContain('animate-spin');
  });

  it('keeps an error until it is dismissed and closes a success after 5 seconds', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.error('Not saved');
      toast.success('Saved');
    });

    const alert = await screen.findByRole('alert');
    expect(alert.textContent).toContain('Not saved');
    await advance(4_900);
    expect(screen.queryByText('Saved')).not.toBeNull();

    await advance(200);
    expect(screen.queryByText('Saved')).toBeNull();

    await advance(60_000);
    expect(screen.getByRole('alert').textContent).toContain('Not saved');

    fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Dismiss' }));
    await advance(0);
    expect(screen.queryByText('Not saved')).toBeNull();
  });

  it('folds the oldest of four toasts into "1 more" and of five into "2 more"', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('One');
      toast.success('Two');
      toast.success('Three');
      toast.success('Four');
    });

    expect(await screen.findByText('1 more')).toBeTruthy();
    const shown = (texts: string[]) =>
      screen
        .getAllByRole('status')
        .filter((element) => !element.hasAttribute('data-limited'))
        .map((element) => texts.find((text) => element.textContent.includes(text)))
        .sort();
    expect(shown(['One', 'Two', 'Three', 'Four'])).toEqual(['Four', 'Three', 'Two']);
    expect(screen.getByText('One').closest('[data-limited]')).not.toBeNull();

    act(() => {
      toast.success('Five');
    });

    expect(await screen.findByText('2 more')).toBeTruthy();
    expect(screen.queryByText('1 more')).toBeNull();
    expect(shown(['One', 'Two', 'Three', 'Four', 'Five'])).toEqual(['Five', 'Four', 'Three']);
  });

  it('keeps a success toast past 5 seconds while the pointer is on the stack', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    await screen.findByText('Saved');
    fireEvent.mouseEnter(screen.getByRole('region', { name: 'Notifications' }));
    await advance(6_000);
    expect(screen.queryByText('Saved')).not.toBeNull();
  });

  it('closes a success toast after 5 seconds without hover', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
    });

    await screen.findByText('Saved');
    await advance(5_100);
    expect(screen.queryByText('Saved')).toBeNull();
  });

  it('keeps an error and a toast with duration 0 after a minute', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    render(<Toaster />);

    act(() => {
      toast.error('Not saved');
      toast.info('Exporting orders', { duration: 0 });
    });

    await screen.findByText('Exporting orders');
    await advance(60_000);
    expect(screen.queryByText('Not saved')).not.toBeNull();
    expect(screen.queryByText('Exporting orders')).not.toBeNull();
  });

  it('gives a success toast the status role and an error the alert role', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Saved');
      toast.error('Not saved');
    });

    expect((await screen.findByRole('status')).textContent).toContain('Saved');
    expect(screen.getByRole('alert').textContent).toContain('Not saved');
    expect(screen.getAllByRole('status')).toHaveLength(1);
    expect(screen.getAllByRole('alert')).toHaveLength(1);
  });

  it('runs an action and follows a link, and each dismisses the toast', async () => {
    const retry = vi.fn();
    render(<Toaster />);

    act(() => {
      toast.error('Export failed', { action: { label: 'Retry', onClick: retry } });
      toast.success('Export ready', { links: [{ label: 'Download', href: '/lathe/exports/1', newTab: true }] });
    });

    const link = await screen.findByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/exports/1');
    expect(link.getAttribute('target')).toBe('_blank');
    link.addEventListener('click', (event) => {
      event.preventDefault();
    });
    fireEvent.click(link);
    expect(await screen.findByText('Export failed')).toBeTruthy();
    await vi.waitFor(() => {
      expect(screen.queryByText('Export ready')).toBeNull();
    });

    fireEvent.click(within(screen.getByRole('alert')).getByRole('button', { name: 'Retry' }));

    expect(retry).toHaveBeenCalledOnce();
    await vi.waitFor(() => {
      expect(screen.queryByText('Export failed')).toBeNull();
    });
  });
});
