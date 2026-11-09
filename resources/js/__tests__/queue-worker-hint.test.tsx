import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { IMPORT_POLL_MS, ImportModal } from '../actions/ImportModal';
import { RunToasts } from '../actions/RunToasts';
import { listenForRuns, runStore } from '../actions/runStore';
import type { ActionMeta } from '../actions/types';
import { Toaster } from '../components/Toaster';
import type { SelectionDescriptor } from '../index/useRowSelection';
import { pickFile } from './support';

const HINT = 'Waiting for a queue worker. Is one running?';

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });

afterEach(() => {
  for (const run of runStore.get()) {
    runStore.dismiss(run.kind, run.id);
  }
  __panel.toasts.set([]);
  vi.useRealTimers();
  vi.restoreAllMocks();
});

async function advance(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
  });
}

describe('queue worker hint on the import wizard', () => {
  const action = {
    uriKey: 'import',
    name: 'Import',
    cancelButtonText: 'Cancel',
  } as ActionMeta;
  const selection: SelectionDescriptor = { mode: 'ids', ids: [], exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: null, lens: null, via: null };
  const counts = { total_rows: 3, processed_rows: 3, created_rows: 2, updated_rows: 1, skipped_rows: 0, failed_rows: 0, errors_url: null, exception: null, first_errors: [] };

  /** Walks the wizard to a started dry run whose polls answer the status the test holds. */
  async function startCheck(answer: { status: string }) {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
      const step = typeof init?.body === 'string' ? (JSON.parse(init.body) as { fields?: { import_step?: string } }).fields?.import_step : undefined;

      if (url.endsWith('/uploads/file')) {
        return Promise.resolve(json({ upload_id: '01UPLOAD', chunk_size: 5242880 }, 201));
      }

      if (url.endsWith('/chunks/0')) {
        return Promise.resolve(new Response(null, { status: 204 }));
      }

      if (url.endsWith('/complete')) {
        return Promise.resolve(json({ upload_id: '01UPLOAD', name: 'products.csv', size: 15 }));
      }

      if (url === '/lathe/api/imports/7') {
        return Promise.resolve(json({ id: 7, mode: 'dry_run', ...counts, ...answer }));
      }

      if (step === 'upload') {
        const data = { import_id: 7, original_name: 'products.csv', total_rows: 3, headers: ['Name'], preview: [['Mug']], columns: [{ name: 'name', label: 'Name', required: true }], auto_mapping: ['name'] };

        return Promise.resolve(json({ result: { message: null, level: 'success', effect: { type: 'emit', event: 'import:headers-detected', data } }, permitted: 0, skipped: 0, failed: 0, errors: [] }));
      }

      return Promise.resolve(json({ result: { message: 'Validation started.', level: 'success', effect: null }, permitted: 0, skipped: 0, failed: 0, errors: [], import_run: { id: 7 } }));
    });

    const view = render(<ImportModal base="/lathe/api/resources/products" action={action} selection={selection} onClose={() => undefined} />);
    pickFile(screen.getByLabelText('File'), new File(['Name\n'], 'products.csv', { type: 'text/csv' }));
    fireEvent.click(await screen.findByRole('button', { name: 'Continue' }));

    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval'] });
    fireEvent.click(screen.getByRole('button', { name: 'Check 3 rows' }));
    await advance(0);

    return view;
  }

  it('shows after 10 s of a queued import and goes once the check ends', async () => {
    const answer = { status: 'queued' };
    const view = await startCheck(answer);

    await advance(9_000);
    expect(screen.queryByText(HINT)).toBeNull();

    await advance(1_000);
    expect(screen.getByText(HINT)).toBeTruthy();

    answer.status = 'completed';
    await advance(IMPORT_POLL_MS);
    expect(screen.getByText('2 will be created, 1 will be updated, 0 failed')).toBeTruthy();
    expect(screen.queryByText(HINT)).toBeNull();

    view.unmount();
    expect(vi.getTimerCount()).toBe(0);
  });

  it('clears its timers when the wizard unmounts mid-wait', async () => {
    const view = await startCheck({ status: 'queued' });

    await advance(3_000);
    view.unmount();

    expect(vi.getTimerCount()).toBe(0);
  });
});

describe('queue worker hint on a run toast', () => {
  function renderRuns() {
    return render(
      <>
        <RunToasts />
        <Toaster />
      </>,
    );
  }

  it('shows after 10 s of a waiting bulk run and goes once it runs', async () => {
    const stop = listenForRuns();
    // Base UI's toast timers call clearTimeout, so every timer is faked, and real time still passes for the lazy stack.
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const answer = { status: 'waiting', total: 10, processed: 0, failed: 0, skipped: 0 };
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(json(answer)));
    const view = renderRuns();

    act(() => {
      window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 10 } }));
    });

    await advance(9_000);
    expect(screen.queryByText(HINT)).toBeNull();

    await advance(1_000);
    expect(await screen.findByText(HINT)).toBeTruthy();

    answer.status = 'running';
    await advance(2_000);
    await waitFor(() => {
      expect(screen.queryByText(HINT)).toBeNull();
    });

    view.unmount();
    stop();
    expect(vi.getTimerCount()).toBe(0);
  });
});
