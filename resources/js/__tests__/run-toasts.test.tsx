import { __panel, toast } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RUN_POLL_MS, RunToasts } from '../actions/RunToasts';
import { listenForRuns, runStore } from '../actions/runStore';
import { Toaster } from '../components/Toaster';

let stop: () => void = () => undefined;

beforeEach(() => {
  stop = listenForRuns();
  // Base UI's toast timers call clearTimeout, so every timer is faked, and real time still passes for the lazy stack.
  vi.useFakeTimers({ shouldAdvanceTime: true });
});

afterEach(() => {
  stop();
  for (const run of runStore.get()) {
    runStore.dismiss(run.kind, run.id);
  }
  __panel.toasts.set([]);
  vi.useRealTimers();
  vi.restoreAllMocks();
});

const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

// The panel mounts the toaster once; the run toasts push into its stack.
function renderRuns() {
  render(
    <>
      <RunToasts />
      <Toaster />
    </>,
  );
}

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

const poll = () => advance(RUN_POLL_MS);
const stack = () => screen.getByRole('region', { name: 'Notifications' });

describe('run toasts', () => {
  it('follows a queued delete to its end and cancels on request', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(json({ status: 'running', total: 1001, processed: 400, failed: 0, skipped: 0 }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }))
      .mockResolvedValueOnce(json({ status: 'finished', total: 1001, processed: 1001, failed: 0, skipped: 0 }));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 1001, verb: 'delete' } }));
    });
    await poll();

    expect(await screen.findByText('Deleting 400 of 1,001')).toBeTruthy();
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/bulk-runs/01RUN');

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(fetch.mock.calls[1]?.[1]?.method).toBe('DELETE');

    await poll();
    expect(screen.getByText('1,001 resources deleted.')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Cancel' })).toBeNull();
  });

  it('reads the counts of a partly failed run', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total: 1001, processed: 1001, failed: 200, skipped: 0 }));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 1001 } }));
    });
    await poll();

    expect(await screen.findByText('801 succeeded, 200 failed.')).toBeTruthy();
  });

  it('counts the skipped rows of a finished run instead of reading as a success', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total: 1001, processed: 600, failed: 0, skipped: 401 }));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 1001 } }));
    });
    await poll();

    expect(await screen.findByText('600 succeeded, 401 skipped.')).toBeTruthy();
    expect(screen.queryByText('Action was executed successfully.')).toBeNull();
  });

  it('turns a finished export into a Download link', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total_rows: 501, processed_rows: 501, download_url: '/lathe/api/exports/4/download?signature=x' }));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:export-started', { detail: { id: 4 } }));
    });
    expect(await screen.findByText('Export started.')).toBeTruthy();
    await poll();

    expect(screen.getByText('Your export is ready for download.')).toBeTruthy();
    const link = screen.getByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/api/exports/4/download?signature=x');
    expect(link.getAttribute('target')).toBe('_blank');
    expect(screen.getByRole('link', { name: 'View run' }).getAttribute('href')).toBe('/lathe/runs/export/4');
  });

  it('keeps a running export in the toast stack and updates its one toast in place', async () => {
    let status: Record<string, unknown> = { status: 'running', total_rows: 100, processed_rows: 50 };
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(json(status)));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:export-started', { detail: { id: 4 } }));
    });
    await poll();

    expect(await within(stack()).findByText('Exporting 50 of 100')).toBeTruthy();
    expect(screen.queryByRole('region', { name: 'Runs' })).toBeNull();
    expect(within(stack()).queryByRole('button', { name: 'Dismiss' })).toBeNull();

    await advance(60_000);
    expect(within(stack()).getByText('Exporting 50 of 100')).toBeTruthy();

    status = { status: 'finished', total_rows: 100, processed_rows: 100, download_url: '/lathe/api/exports/4/download?signature=x' };
    await poll();

    expect(within(stack()).getByText('Your export is ready for download.')).toBeTruthy();
    expect(within(stack()).getByRole('link', { name: 'Download' })).toBeTruthy();
    expect(screen.queryByText('Exporting 50 of 100')).toBeNull();
    expect(within(stack()).getAllByRole('status')).toHaveLength(1);
  });

  it('keeps a finished import with failed rows and puts its download under the text', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      json({
        status: 'completed',
        mode: 'import',
        total_rows: 2400,
        processed_rows: 2400,
        created_rows: 2000,
        updated_rows: 388,
        skipped_rows: 0,
        failed_rows: 12,
        errors_url: '/lathe/api/imports/7/download-errors?signature=x',
        resource_label: 'Products',
      }),
    );
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:import-started', { detail: { id: 7 } }));
    });
    await poll();

    const message = await within(stack()).findByText('2,388 of 2,400 products imported');
    const link = within(stack()).getByRole('link', { name: 'Download failed rows' });
    expect(link.getAttribute('href')).toBe('/lathe/api/imports/7/download-errors?signature=x');
    // The link shares the text's column, below the message, rather than sitting beside it.
    expect(message.parentElement?.contains(link)).toBe(true);
    expect(message.compareDocumentPosition(link) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();

    await advance(60_000);
    expect(within(stack()).getByText('2,388 of 2,400 products imported')).toBeTruthy();
  });

  it('closes a finished bulk action without failures after 5 seconds', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ status: 'finished', total: 3, processed: 3, failed: 0, skipped: 0 }));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 3, verb: 'action' } }));
    });
    await poll();

    expect(await within(stack()).findByText('Action was executed successfully.')).toBeTruthy();
    await advance(5_100);

    expect(screen.queryByText('Action was executed successfully.')).toBeNull();
    expect(runStore.get()).toHaveLength(0);
  });

  it('counts a running toast toward the three on screen', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(json({ status: 'running', total_rows: 100, processed_rows: 50 })));
    renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:export-started', { detail: { id: 4 } }));
    });
    await within(await screen.findByRole('region', { name: 'Notifications' })).findByText('Export started.');

    act(() => {
      toast.success('One');
      toast.success('Two');
      toast.success('Three');
    });

    expect(await within(stack()).findByText('1 more')).toBeTruthy();
  });
});
