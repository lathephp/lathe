import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

const importStatus = {
  id: 5,
  status: 'completed',
  mode: 'import',
  total_rows: 3,
  processed_rows: 3,
  created_rows: 2,
  updated_rows: 0,
  skipped_rows: 0,
  failed_rows: 1,
  errors_url: '/lathe/api/imports/5/download-errors?signature=x',
  exception: null,
  first_errors: [],
};

describe('the run page', () => {
  it('offers the Download link of a finished export', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ id: 4, status: 'finished', total_rows: 501, processed_rows: 501, download_url: '/lathe/api/exports/4/download?signature=x', exception: null }));
    renderPage('Runs/Show', { type: 'export', id: 4 });

    const link = await screen.findByRole('link', { name: 'Download' });
    expect(link.getAttribute('href')).toBe('/lathe/api/exports/4/download?signature=x');
    expect(link.getAttribute('target')).toBe('_blank');
  });

  it('says a failed export failed, with the exception text', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json({ id: 4, status: 'failed', total_rows: 501, processed_rows: 100, download_url: null, exception: 'Disk full' }));
    renderPage('Runs/Show', { type: 'export', id: 4 });

    expect(await screen.findByText('Your export failed.')).toBeTruthy();
    expect(screen.getByText('Disk full')).toBeTruthy();
  });

  it('shows the counts and the failed rows of an import', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(json(importStatus));
    renderPage('Runs/Show', { type: 'import', id: 5 });

    expect(await screen.findByText('2 created, 0 updated, 0 skipped, 1 failed.')).toBeTruthy();
    expect(screen.getByRole('link', { name: 'Download Failed Rows' }).getAttribute('href')).toBe(importStatus.errors_url);
  });

  it('resumes a failed import', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementation((_url, init) =>
        Promise.resolve(init?.method === 'POST' ? new Response(null, { status: 202 }) : json({ ...importStatus, status: 'failed', exception: 'The job ran too long.' })),
      );
    renderPage('Runs/Show', { type: 'import', id: 5 });

    expect(await screen.findByText('The job ran too long.')).toBeTruthy();
    fireEvent.click(await screen.findByRole('button', { name: 'Resume' }));

    await waitFor(() => {
      expect(fetch.mock.calls.map(([url, init]) => `${url as string} ${String(init?.method)}`)).toContain('/lathe/api/imports/5/resume POST');
    });
  });
});
