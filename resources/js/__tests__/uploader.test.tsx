import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { FormField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

const MB = 1024 * 1024;

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'avatar',
    attribute: 'avatar',
    label: 'Avatar',
    component: 'image-field',
    sortable: false,
    computed: false,
    nullable: false,
    stacked: false,
    fullWidth: false,
    columnSpan: null,
    indexWidth: null,
    borderless: false,
    compact: false,
    textAlign: 'left',
    placeholder: null,
    help: null,
    helpWidth: null,
    wrapping: false,
    section: null,
    autocomplete: null,
    meta: {},
    maxSize: 5,
    acceptedTypes: ['image/png', 'image/jpeg'],
    maxWidth: null,
    deletable: true,
    ...overrides,
  };
}

interface Call {
  method: string;
  url: string;
  body: unknown;
}

/** A fake upload server: records each request and answers the protocol, with hooks per route. */
function server(options: { chunk?: (index: number) => Response | Promise<Response>; status?: () => Response } = {}) {
  const calls: Call[] = [];
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const method = init?.method ?? 'GET';
    const body = init?.body;
    calls.push({ method, url, body: typeof body === 'string' ? JSON.parse(body) : body });

    const chunk = /\/chunks\/(\d+)$/.exec(url);

    if (chunk !== null) {
      return Promise.resolve(options.chunk?.(Number(chunk[1])) ?? new Response(null, { status: 204 }));
    }

    if (method === 'POST' && url.endsWith('/complete')) {
      return Promise.resolve(new Response(JSON.stringify({ upload_id: '01UPLOAD', name: 'avatar.png', size: 1 })));
    }

    if (method === 'POST') {
      return Promise.resolve(new Response(JSON.stringify({ upload_id: '01UPLOAD', chunk_size: MB }), { status: 201 }));
    }

    if (method === 'GET') {
      return Promise.resolve(options.status?.() ?? new Response(JSON.stringify({ size: 0, received: [] })));
    }

    return Promise.resolve(new Response(JSON.stringify({ id: 7, redirect: 'users/7' })));
  });

  return { fetch, calls, chunks: () => calls.filter((call) => /\/chunks\/\d+$/.test(call.url)) };
}

function renderField(field: FieldSchema = schema(), onChange: (value: unknown) => void = () => undefined, value: unknown = null, disabled = false) {
  return render(
    <>
      <label htmlFor="avatar">Avatar</label>
      <FormField field={{ ...field, value }} value={value} onChange={onChange} id="avatar" describedBy={undefined} invalid={false} disabled={disabled} />
    </>,
  );
}

function choose(file: File) {
  fireEvent.change(screen.getByLabelText('Avatar'), { target: { files: [file] } });
}

function sized(bytes: number, name = 'avatar.png', type = 'image/png', lastModified = 1_700_000_000_000): File {
  return new File([new Uint8Array(bytes)], name, { type, lastModified });
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'users' }, mode: 'update', id: 7 });
  window.sessionStorage.clear();
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('chunked uploader', () => {
  it('uploads a 3 MB png in chunks, fills the progress bar and sets the upload id', async () => {
    const { calls, chunks } = server();
    const onChange = vi.fn();
    renderField(schema(), onChange);

    choose(sized(3 * MB));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('01UPLOAD');
    });
    expect(calls[0]).toEqual({ method: 'POST', url: '/lathe/api/resources/users/uploads/avatar', body: { name: 'avatar.png', size: 3 * MB, mime: 'image/png', resource_id: 7 } });
    expect(chunks().map((call) => [call.method, call.url, (call.body as Blob).size])).toEqual([
      ['PUT', '/lathe/api/uploads/01UPLOAD/chunks/0', MB],
      ['PUT', '/lathe/api/uploads/01UPLOAD/chunks/1', MB],
      ['PUT', '/lathe/api/uploads/01UPLOAD/chunks/2', MB],
    ]);
    expect(calls.at(-1)?.url).toBe('/lathe/api/uploads/01UPLOAD/complete');
    expect(screen.getByRole('progressbar').getAttribute('aria-valuenow')).toBe('100');
  });

  it('refuses a 12 MB file on a 5 MB field before any request', async () => {
    const { fetch } = server();
    renderField();

    choose(sized(12 * MB));

    expect(await screen.findByText('File exceeds the 5 MB limit')).toBeTruthy();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('answers the size first for the plan file-12mb.bin, a type the image field does not take', async () => {
    const { fetch } = server();
    renderField();

    choose(sized(12 * MB, 'file-12mb.bin', 'application/octet-stream'));

    expect(await screen.findByText('File exceeds the 5 MB limit')).toBeTruthy();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('refuses a type the field does not accept before any request', () => {
    const { fetch } = server();
    renderField();

    choose(sized(10, 'notes.txt', 'text/plain'));

    expect(screen.getByText('The file type is not one of: image/png, image/jpeg.')).toBeTruthy();
    expect(fetch).not.toHaveBeenCalled();
  });

  it('downsizes a 1600 px image to maxWidth before the first chunk', async () => {
    const { calls, chunks } = server();
    const resized = new Blob([new Uint8Array(100_000)], { type: 'image/png' });
    const canvases: number[][] = [];
    vi.stubGlobal('createImageBitmap', () => Promise.resolve({ width: 1600, height: 900, close: () => undefined }));
    vi.stubGlobal(
      'OffscreenCanvas',
      class {
        constructor(width: number, height: number) {
          canvases.push([width, height]);
        }

        getContext() {
          return { drawImage: () => undefined };
        }

        convertToBlob() {
          return Promise.resolve(resized);
        }
      },
    );
    const onChange = vi.fn();
    renderField(schema({ maxWidth: 800 }), onChange);

    choose(sized(400_000));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('01UPLOAD');
    });
    expect(canvases).toEqual([[800, 450]]);
    expect((calls[0]?.body as { size: number }).size).toBe(100_000);
    expect(chunks().map((call) => (call.body as Blob).size)).toEqual([100_000]);
  });

  it('retries a failing chunk 3 times, then toasts with Retry', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout'] });
    let failing = true;
    const { chunks } = server({ chunk: () => (failing ? new Response('', { status: 500 }) : new Response(null, { status: 204 })) });
    const onChange = vi.fn();
    renderField(schema(), onChange);

    choose(sized(MB));
    await act(async () => {
      await vi.advanceTimersByTimeAsync(10_000);
    });

    expect(chunks()).toHaveLength(4);
    const [toast] = __panel.toasts.get();
    expect(toast?.message).toBe('The upload failed.');
    expect(toast?.action?.label).toBe('Retry');

    failing = false;
    await act(async () => {
      toast?.action?.onClick();
      await vi.advanceTimersByTimeAsync(1_000);
    });

    expect(onChange).toHaveBeenCalledWith('01UPLOAD');
  });

  it('resumes an upload after a reload without sending the received chunks again', async () => {
    window.sessionStorage.setItem(
      `lathe-upload:${window.location.pathname}:avatar`,
      JSON.stringify({ upload_id: '01UPLOAD', name: 'avatar.png', size: 3 * MB, lastModified: 1_700_000_000_000, attribute: 'avatar', chunk_size: MB }),
    );
    const { calls, chunks } = server({ status: () => new Response(JSON.stringify({ size: 3 * MB, received: [0, 1] })) });
    const onChange = vi.fn();
    renderField(schema(), onChange);

    choose(sized(3 * MB));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('01UPLOAD');
    });
    expect(calls[0]).toMatchObject({ method: 'GET', url: '/lathe/api/uploads/01UPLOAD' });
    expect(calls.some((call) => call.url.endsWith('/uploads/avatar'))).toBe(false);
    expect(chunks().map((call) => call.url)).toEqual(['/lathe/api/uploads/01UPLOAD/chunks/2']);
  });

  it('keeps the upload in session storage while it runs and leaves the pending row on unmount', async () => {
    const { calls } = server({ chunk: () => new Promise<Response>(() => undefined) });
    const { unmount } = renderField();

    choose(sized(3 * MB));

    await waitFor(() => {
      expect(window.sessionStorage.getItem(`lathe-upload:${window.location.pathname}:avatar`)).toBe(
        JSON.stringify({ upload_id: '01UPLOAD', name: 'avatar.png', size: 3 * MB, lastModified: 1_700_000_000_000, attribute: 'avatar', chunk_size: MB }),
      );
    });
    unmount();

    expect(calls.some((call) => call.method === 'DELETE')).toBe(false);
  });

  it('shows the field limit when the web server answers 413', async () => {
    server({ chunk: () => new Response('<html>Too Large</html>', { status: 413 }) });
    renderField();

    choose(sized(MB));

    expect(await screen.findByText('File exceeds the 5 MB limit')).toBeTruthy();
  });

  it('disables the file input of a readonly field', () => {
    renderField(schema(), () => undefined, null, true);

    expect(screen.getByLabelText<HTMLInputElement>('Avatar').disabled).toBe(true);
  });

  it('starts a repeater row upload with its scoped attribute', async () => {
    const { calls } = server();
    const onChange = vi.fn();
    render(
      <>
        <label htmlFor="avatar">Avatar</label>
        <FormField
          field={{ ...schema({ attribute: 'items.0.photo', component: 'file-field', acceptedTypes: [] }), value: null }}
          value={null}
          onChange={onChange}
          id="avatar"
          describedBy={undefined}
          invalid={false}
          disabled={false}
        />
      </>,
    );

    choose(sized(10, 'photo.png'));

    await waitFor(() => {
      expect(onChange).toHaveBeenCalledWith('01UPLOAD');
    });
    expect(calls[0]?.url).toBe('/lathe/api/resources/users/uploads/items.0.photo');
  });
});

describe('file fields on submit', () => {
  const stored = { path: 'avatars/a.png', name: 'a.png', size: 120, preview: '/storage/avatars/a.png', thumbnail: null, downloadable: true };
  const resource = { uriKey: 'users', label: 'Users', singularLabel: 'User', abilities: { create: true } };

  function renderUserForm(value: unknown) {
    return renderPage('Resources/Form', {
      resource,
      mode: 'update',
      id: 7,
      title: 'Ann',
      breadcrumbs: [],
      form: {
        fields: [{ ...schema(), value }],
        layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['avatar'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
        updatedAt: '2026-10-14T09:00:00.000Z',
      },
      submitLabel: 'Update User',
    });
  }

  function submitted(calls: Call[]): Record<string, unknown> {
    return calls.find((call) => call.url === '/lathe/api/resources/users/7')?.body as Record<string, unknown>;
  }

  it('sends the removed flag when a stored file is removed', async () => {
    const { calls } = server();
    vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderUserForm(stored);

    fireEvent.click(screen.getByRole('button', { name: 'Remove' }));
    fireEvent.click(screen.getByRole('button', { name: 'Update User' }));

    await waitFor(() => {
      expect(submitted(calls)).toEqual({ avatar: null, avatar_removed: 1, _updated_at: '2026-10-14T09:00:00.000Z' });
    });
  });
});

describe('file sub-fields', () => {
  it('starts the upload of a file inside a repeater row under its scoped key', async () => {
    const { RepeaterPathContext } = await import('../fields/repeater/errors');
    const { FileControl } = await import('../fields/upload/file');
    const { render, fireEvent, waitFor } = await import('@testing-library/react');
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ message: 'stop' }), { status: 422 }));
    const field = { key: 'photo', attribute: 'photo', label: 'Photo', component: 'file-field', maxSize: 10, acceptedTypes: [] } as never;

    __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'orders' }, mode: 'update', id: 12 });
    const { container } = render(
      <RepeaterPathContext value="items.0">
        <FileControl field={field} value={null} onChange={() => undefined} id="photo" describedBy={undefined} invalid={false} disabled={false} media={() => null} />
      </RepeaterPathContext>,
    );
    const input = container.querySelector('input[type="file"]');
    if (input === null) throw new Error('no file input');
    fireEvent.change(input, { target: { files: [new File(['x'], 'a.txt', { type: 'text/plain' })] } });

    await waitFor(() => {
      expect(fetch).toHaveBeenCalled();
    });
    expect(fetch.mock.calls[0]?.[0] as string).toContain('/uploads/items.0.photo');
  });
});
