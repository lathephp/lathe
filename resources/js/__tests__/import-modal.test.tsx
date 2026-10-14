import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { IMPORT_POLL_MS, ImportModal } from '../actions/ImportModal';
import type { ActionMeta } from '../actions/types';
import type { SelectionDescriptor } from '../index/useRowSelection';

const action: ActionMeta = {
  uriKey: 'import',
  name: 'Import',
  icon: null,
  description: null,
  group: null,
  destructive: false,
  standalone: true,
  sole: false,
  pinned: false,
  withoutConfirmation: false,
  confirmText: null,
  confirmButtonText: 'Run Action',
  cancelButtonText: 'Cancel',
  size: '2xl',
  style: 'window',
  asBlob: false,
  showOnIndex: true,
  showOnDetail: false,
  showOnTableRow: false,
};

const selection: SelectionDescriptor = { mode: 'ids', ids: [], exclusions: [], q: '', filters: {}, tab: null, saved_tab: null, trashed: null, lens: null, via: null };

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });

const message = (text: string) => ({ message: text, level: 'success', effect: null });

const counts = { total_rows: 3, processed_rows: 3, created_rows: 2, updated_rows: 1, skipped_rows: 0, failed_rows: 0, errors_url: null, exception: null, first_errors: [] };

interface Call {
  method: string;
  url: string;
  body: unknown;
}

/** The import endpoints: the chunked upload, the three steps of POST /action and the status poll. */
function server() {
  const calls: Call[] = [];
  let polls = 0;

  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const method = init?.method ?? 'GET';
    const body = typeof init?.body === 'string' ? (JSON.parse(init.body) as unknown) : init?.body;
    calls.push({ method, url, body });

    if (url === '/lathe/api/resources/products/actions/import/uploads/file') {
      return Promise.resolve(json({ upload_id: '01UPLOAD', chunk_size: 5242880 }, 201));
    }

    if (url === '/lathe/api/uploads/01UPLOAD/chunks/0') {
      return Promise.resolve(new Response(null, { status: 204 }));
    }

    if (url === '/lathe/api/uploads/01UPLOAD/complete') {
      return Promise.resolve(json({ upload_id: '01UPLOAD', name: 'products.csv', size: 98 }));
    }

    if (url === '/lathe/api/imports/7') {
      polls++;

      return Promise.resolve(json({ id: 7, mode: 'dry_run', ...counts, status: polls === 1 ? 'processing' : 'completed' }));
    }

    const step = (body as { fields?: { import_step?: string } } | undefined)?.fields?.import_step;

    if (step === 'upload') {
      return Promise.resolve(
        json({
          result: {
            message: null,
            level: 'success',
            effect: {
              type: 'emit',
              event: 'import:headers-detected',
              data: {
                import_id: 7,
                original_name: 'products.csv',
                headers: ['Name', 'SKU', 'Price'],
                preview: [
                  ['Trail Mug', 'SKU-90001', '12.50'],
                  ['Camp Kettle', 'SKU-90002', '34.00'],
                  ['Updated Product One', 'SKU-00001', '19.99'],
                ],
                columns: [
                  { name: 'name', label: 'Name', required: true },
                  { name: 'sku', label: 'Sku', required: true },
                  { name: 'price', label: 'Price', required: true },
                ],
                auto_mapping: ['name', 'sku', 'price'],
              },
            },
          },
          permitted: 0,
          skipped: 0,
          failed: 0,
          errors: [],
        }),
      );
    }

    const text = step === 'dry_run' ? 'Validation started.' : 'Import started.';

    return Promise.resolve(json({ result: message(text), permitted: 0, skipped: 0, failed: 0, errors: [], import_run: { id: 7 } }));
  });

  return { calls, step: (name: string) => calls.find((call) => (call.body as { fields?: { import_step?: string } } | undefined)?.fields?.import_step === name) };
}

async function flush() {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(0);
  });
}

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('import modal', () => {
  it('uploads, maps, validates and imports a file', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'setInterval', 'clearTimeout', 'clearInterval'] });
    const { calls, step } = server();
    const onClose = vi.fn();
    const started = vi.fn();
    window.addEventListener('lathe:import-started', started);
    render(<ImportModal base="/lathe/api/resources/products" action={action} selection={selection} onClose={onClose} />);

    // 1. Choose the file: it uploads in chunks, then the upload step reads its headers.
    const file = new File(['Name,SKU,Price\n'], 'products.csv', { type: 'text/csv' });
    fireEvent.change(screen.getByLabelText('File'), { target: { files: [file] } });
    await flush();

    expect(calls.slice(0, 3).map((call) => `${call.method} ${call.url}`)).toEqual([
      'POST /lathe/api/resources/products/actions/import/uploads/file',
      'PUT /lathe/api/uploads/01UPLOAD/chunks/0',
      'POST /lathe/api/uploads/01UPLOAD/complete',
    ]);
    expect(calls[0]?.body).toEqual({ name: 'products.csv', size: 15, mime: 'text/csv' });
    expect(step('upload')?.body).toEqual({ action: 'import', selection, fields: { import_step: 'upload', file: '01UPLOAD' } });

    // 2. The mapping comes prefilled; a required column set to Skip holds Continue back.
    const price = screen.getByLabelText<HTMLSelectElement>('Price');
    expect(screen.getByLabelText<HTMLSelectElement>('Name').value).toBe('name');
    expect(screen.getByLabelText<HTMLSelectElement>('SKU').value).toBe('sku');
    expect(price.value).toBe('price');
    expect(screen.getByText('Camp Kettle')).toBeTruthy();

    fireEvent.change(price, { target: { value: '' } });
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Continue' }).disabled).toBe(true);
    fireEvent.change(price, { target: { value: 'price' } });
    expect(screen.getByRole<HTMLButtonElement>('button', { name: 'Continue' }).disabled).toBe(false);
    fireEvent.click(screen.getByRole('button', { name: 'Continue' }));

    // 3. Validate runs the dry run and polls the import until it ends.
    fireEvent.click(screen.getByRole('button', { name: 'Validate' }));
    await flush();
    expect(step('dry_run')?.body).toEqual({ action: 'import', selection, fields: { import_step: 'dry_run', import_id: 7, column_map: ['name', 'sku', 'price'] } });

    await act(async () => {
      await vi.advanceTimersByTimeAsync(IMPORT_POLL_MS);
    });
    expect(screen.queryByText('2 will be created, 1 will be updated, 0 failed')).toBeNull();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(IMPORT_POLL_MS);
    });
    expect(IMPORT_POLL_MS).toBe(2000);
    expect(screen.getByText('2 will be created, 1 will be updated, 0 failed')).toBeTruthy();
    expect(screen.queryByRole('link', { name: 'Download Failed Rows' })).toBeNull();

    // 4. Import runs the real import, hands it to the progress toast and closes.
    fireEvent.click(screen.getByRole('button', { name: 'Import' }));
    await flush();

    expect(step('import')?.body).toEqual({ action: 'import', selection, fields: { import_step: 'import', import_id: 7, column_map: ['name', 'sku', 'price'] } });
    expect(started).toHaveBeenCalledTimes(1);
    expect((started.mock.calls[0]?.[0] as CustomEvent).detail).toEqual({ id: 7 });
    expect(onClose).toHaveBeenCalled();
    window.removeEventListener('lathe:import-started', started);
  });
});
