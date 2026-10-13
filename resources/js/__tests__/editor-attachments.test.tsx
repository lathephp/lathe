import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

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
        body: `<p><img src="${URL_STORED}" alt="pasted.png"></p>`,
        bodyDraftId: DRAFT,
        summary: '',
      });
    });
  });

  it('inserts an uploaded image into the Markdown source from the image button', async () => {
    server();
    renderPostForm();

    const input = await screen.findByLabelText<HTMLInputElement>('Upload image');
    fireEvent.change(input, { target: { files: [png()] } });

    await waitFor(() => {
      expect(screen.getByRole<HTMLTextAreaElement>('textbox', { name: 'Summary' }).value).toBe(`![pasted.png](${URL_STORED})`);
    });
  });
});
