import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { RUN_POLL_MS, RunToasts } from '../actions/RunToasts';

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

async function poll() {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(RUN_POLL_MS);
  });
}

describe('run toasts', () => {
  it('follows a queued delete to its end and cancels on request', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'setTimeout'] });
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ status: 'running', total: 1001, processed: 400, failed: 0, skipped: 0 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(json({ status: 'finished', total: 1001, processed: 1001, failed: 0, skipped: 0 }));
    render(<RunToasts />);

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 1001, verb: 'delete' } }));
    });
    await poll();

    expect(screen.getByText('Deleting 400 of 1,001')).toBeTruthy();
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/bulk-runs/01RUN');

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(fetch.mock.calls[1]?.[1]?.method).toBe('DELETE');

    await poll();
    expect(screen.getByText('1,001 resources deleted.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Cancel' })).toBeNull();
  });

  it('reads the counts of a partly failed run', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'setTimeout'] });
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total: 1001, processed: 1001, failed: 200, skipped: 0 }));
    render(<RunToasts />);

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 1001 } }));
    });
    await poll();

    expect(screen.getByText('801 succeeded, 200 failed.')).toBeTruthy();
  });

  it('turns a finished export into a Download link', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval', 'setTimeout'] });
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total_rows: 501, processed_rows: 501, download_url: '/lathe/api/exports/4/download?signature=x' }));
    render(<RunToasts />);

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:export-started', { detail: { id: 4 } }));
    });
    expect(screen.getByText('Export started.')).toBeTruthy();
    await poll();

    expect(screen.getByText('Your export is ready for download.')).toBeTruthy();
    const link = screen.getByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/api/exports/4/download?signature=x');
    expect(link.getAttribute('target')).toBe('_blank');
    expect(screen.getByRole('link', { name: 'View run' }).getAttribute('href')).toBe('/lathe/runs/export/4');
  });
});
