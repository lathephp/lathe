import { toast } from '@lathephp/core';
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

  it('pushes out the oldest of four toasts', async () => {
    render(<Toaster />);

    act(() => {
      toast.info('One');
      toast.info('Two');
      toast.warning('Three');
      toast.success('Four');
    });

    expect(await screen.findByText('Four')).toBeTruthy();
    expect(screen.getAllByRole('status').map((element) => element.textContent).sort()).toEqual([
      expect.stringContaining('Four'),
      expect.stringContaining('Three'),
      expect.stringContaining('Two'),
    ]);
    expect(screen.queryByText('One')).toBeNull();
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
