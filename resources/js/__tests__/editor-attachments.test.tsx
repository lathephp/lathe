import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema } from '../fields/types';
import { pickFile, renderPage } from './support';

const DRAFT = '0b4f1c1e-5d2c-4a52-9c39-0d1e6a3b7f10';
const URL_STORED = '/storage/pasted.png';

function schema(attribute: string, component: string): FieldSchema {
  return {
    key: attribute,
    attribute,
    label: attribute === 'body' ? 'Body' : 'Summary',
    component,
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
    withFiles: true,
  };
}

interface Call {
  method: string;
  url: string;
  body: unknown;
}

/** A fake panel API answering the draft id, the chunked protocol, the attach and the save. */
function server() {
  const calls: Call[] = [];
  vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
    const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
    const method = init?.method ?? 'GET';
    const body = init?.body;
    calls.push({ method, url, body: typeof body === 'string' ? JSON.parse(body) : body });
    const json = (data: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(data), { status }));

    if (url.endsWith('/draft-id')) {
      return json({ draft_id: DRAFT });
    }

    if (url.includes('/chunks/')) {
      return Promise.resolve(new Response(null, { status: 204 }));
    }

    if (url.endsWith('/complete')) {
      return json({ upload_id: '01IMG', name: 'pasted.png', size: 4 });
    }

    if (/\/uploads\/\w+$/.test(url)) {
      return json({ upload_id: '01IMG', chunk_size: 1024 * 1024 }, 201);
    }

    if (/\/field-attachment\/\w+$/.test(url)) {
      return json({ url: URL_STORED });
    }

    return json({ id: 3, redirect: '/lathe/resources/posts/3' }, 201);
  });

  return calls;
}

function renderPostForm() {
  return renderPage('Resources/Form', {
    resource: { uriKey: 'posts', label: 'Posts', singularLabel: 'Post', abilities: { create: true } },
    mode: 'create',
    id: null,
    title: 'Create Post',
    breadcrumbs: [],
    form: {
      fields: [
        { ...schema('body', 'tiptap-field'), value: '' },
        { ...schema('summary', 'markdown-field'), value: '' },
      ],
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['body', 'summary'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
    },
    submitLabel: 'Create Post',
  });
}

function png(): File {
  return new File([new Uint8Array([137, 80, 78, 71])], 'pasted.png', { type: 'image/png' });
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'posts' }, mode: 'create', id: null });
  window.sessionStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('editor attachments', () => {
  it('uploads a pasted image under the draft id, inserts it and submits the draft id', async () => {
    const calls = server();
    vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPostForm();

    const editor = await screen.findByRole('textbox', { name: 'Body' });
    fireEvent.paste(editor, { clipboardData: { files: [png()], types: ['Files'], items: [], getData: () => '' } });

    await waitFor(() => {
      expect(editor.querySelector('img')?.getAttribute('src')).toBe(URL_STORED);
    });
    expect(calls.find((call) => call.url === '/lathe/api/resources/posts/uploads/body')?.body).toEqual({ name: 'pasted.png', size: 4, mime: 'image/png', draft_id: DRAFT });
    expect(calls.find((call) => call.url === '/lathe/api/resources/posts/field-attachment/body')?.body).toEqual({ upload_id: '01IMG' });

    fireEvent.click(screen.getByRole('button', { name: 'Create Post' }));

    await waitFor(() => {
      expect(calls.find((call) => call.url === '/lathe/api/resources/posts' && call.method === 'POST')?.body).toEqual({
        body: `<img src="${URL_STORED}" alt="pasted.png"><p></p>`,
        bodyDraftId: DRAFT,
        summary: '',
      });
    });
  });

  it('gives each of two images pasted one after the other its own image, whichever upload ends first', async () => {
    const calls: Call[] = [];
    let releaseFirst: (response: Response) => void = () => undefined;
    let drafts = 0;
    vi.spyOn(globalThis, 'fetch').mockImplementation((input, init) => {
      const url = typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
      const method = init?.method ?? 'GET';
      const body = typeof init?.body === 'string' ? (JSON.parse(init.body) as Record<string, unknown>) : init?.body;
      calls.push({ method, url, body });
      const json = (data: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(data), { status }));

      if (url.endsWith('/draft-id')) {
        drafts += 1;

        return json({ draft_id: `draft-${String(drafts)}` });
      }

      if (url.includes('/uploads/01FIRST/chunks/')) {
        return new Promise<Response>((resolve) => (releaseFirst = resolve));
      }

      if (url.includes('/chunks/')) {
        return Promise.resolve(new Response(null, { status: 204 }));
      }

      if (url.endsWith('/complete')) {
        return json({});
      }

      if (/\/uploads\/\w+$/.test(url)) {
        const name = (body as { name?: string }).name;

        return json({ upload_id: name === 'first.png' ? '01FIRST' : '01SECOND', chunk_size: 1024 * 1024 }, 201);
      }

      if (/\/field-attachment\/\w+$/.test(url)) {
        return json({ url: (body as { upload_id?: string }).upload_id === '01FIRST' ? '/storage/first.png' : '/storage/second.png' });
      }

      return json({ id: 3, redirect: '/lathe/resources/posts/3' }, 201);
    });
    renderPostForm();
    const image = (name: string) => new File([new Uint8Array([137, 80, 78, 71])], name, { type: 'image/png' });
    const paste = (file: File) => ({ clipboardData: { files: [file], types: ['Files'], items: [], getData: () => '' } });

    const editor = await screen.findByRole('textbox', { name: 'Body' });
    fireEvent.paste(editor, paste(image('first.png')));
    await waitFor(() => {
      expect(calls.some((call) => call.url.includes('/uploads/01FIRST/chunks/'))).toBe(true);
    });
    fireEvent.paste(editor, paste(image('second.png')));
    await waitFor(() => {
      expect(editor.querySelector('img[alt="second.png"]')?.getAttribute('src')).toBe('/storage/second.png');
    });
    releaseFirst(new Response(null, { status: 204 }));

    await waitFor(() => {
      expect(editor.querySelector('img[alt="first.png"]')?.getAttribute('src')).toBe('/storage/first.png');
    });
    expect(editor.querySelectorAll('img')).toHaveLength(2);
    // Both images belong to the one draft the save attaches.
    expect(drafts).toBe(1);
  });

  it('inserts an uploaded image into the Markdown source from the image button', async () => {
    server();
    renderPostForm();

    const input = pickFile(await screen.findByRole('button', { name: 'Upload image' }), png());

    expect(document.body.querySelector('input[type="file"]')).toBeNull();
    expect(input.accept).toBe('image/png,image/jpeg,image/gif,image/webp');

    await waitFor(() => {
      expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Summary' }).value).toBe(`![pasted.png](${URL_STORED})`);
    });
  });
});
