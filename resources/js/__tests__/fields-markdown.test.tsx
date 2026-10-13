import type { SafeHtml } from '@lathephp/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, registries } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'body',
    attribute: 'body',
    label: 'Body',
    component: 'markdown-field',
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
    ...overrides,
  };
}

const html = (value: string) => ({ __html: value }) as unknown as SafeHtml;

function renderDetail(value: unknown, display: string) {
  return render(<DetailField field={{ ...schema(), value, display }} />).container;
}

function renderForm(value: unknown, mode: 'create' | 'update' = 'create') {
  return renderPage('Resources/Form', {
    resource: { uriKey: 'posts', label: 'Posts', singularLabel: 'Post', abilities: { create: true } },
    mode,
    id: mode === 'update' ? 7 : null,
    title: 'Create Post',
    breadcrumbs: [],
    form: {
      fields: [{ ...schema(), value }],
      layout: [
        {
          type: 'panel',
          attribute: 'details',
          name: 'Details',
          fields: ['body'],
          toolbar: true,
          collapsible: false,
          collapsed: false,
          hideHeading: false,
          headingInCard: false,
          help: null,
          limit: null,
          columns: null,
          columnSpan: null,
          borderless: false,
        },
      ],
    },
    submitLabel: 'Create Post',
  });
}

function respond(preview: string) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify({ preview: { __html: preview } }), { status: 200 }));
}

describe('markdown-field', () => {
  it('has a detail and a form renderer and none on the index', () => {
    expect([registries.index.has('markdown-field'), registries.detail.has('markdown-field'), registries.form.has('markdown-field')]).toEqual([false, true, true]);
  });

  it('renders the sanitized detail value with the prose class', () => {
    const container = renderDetail(html('<p><strong>bold</strong></p>'), 'bold');

    expect(container.querySelector('.prose')?.innerHTML).toBe('<p><strong>bold</strong></p>');
    expect(screen.queryByRole('button', { name: 'Show Content' })).toBeNull();
  });

  it('collapses a value over 10 lines behind Show Content', () => {
    const lines = Array.from({ length: 11 }, (_, index) => `line ${String(index + 1)}`);
    renderDetail(html(`<p>${lines.join('<br />')}</p>`), lines.join('\n'));

    fireEvent.click(screen.getByRole('button', { name: 'Show Content' }));

    expect(screen.getByRole('button', { name: 'Hide Content' })).toBeDefined();
  });

  it('shows the four values on detail and in the editor', async () => {
    expect(renderDetail(5, '5').textContent).toBe('5');
    expect(renderDetail('x', 'x').textContent).toBe('x');
    expect(renderDetail(true, 'Yes').textContent).toBe('Yes');
    expect(renderDetail(null, '').textContent).toBe('—');

    for (const [value, shown] of [
      [5, '5'],
      ['x', 'x'],
      [true, 'true'],
      [null, ''],
    ] as const) {
      const { unmount } = renderForm(value);
      expect((await screen.findByLabelText<HTMLTextAreaElement>(/Body/)).value).toBe(shown);
      unmount();
    }

    renderForm('**old**');
    const textarea = await screen.findByLabelText<HTMLTextAreaElement>(/Body/);
    fireEvent.change(textarea, { target: { value: '**bold**' } });

    expect(textarea.value).toBe('**bold**');
  });

  it('previews the typed source through the create preview endpoint', async () => {
    const fetch = respond('<p><strong>bold</strong></p>');
    renderForm('');
    fireEvent.change(await screen.findByLabelText(/Body/), { target: { value: '**bold**' } });

    fireEvent.click(screen.getByRole('tab', { name: 'Preview' }));

    await waitFor(() => {
      expect(document.querySelector('.prose')?.innerHTML).toBe('<p><strong>bold</strong></p>');
    });
    const [url, init] = fetch.mock.calls[0] ?? [];
    expect([url, init?.method, init?.body]).toEqual(['/lathe/api/resources/posts/field/body/preview', 'POST', '{"value":"**bold**"}']);

    fireEvent.click(screen.getByRole('tab', { name: 'Write' }));
    expect(screen.getByLabelText<HTMLTextAreaElement>(/Body/).value).toBe('**bold**');
  });

  it('previews on the edit form through the record URL', async () => {
    const fetch = respond('<h1>Hi</h1>');
    renderForm('# Hi', 'update');
    await screen.findByLabelText(/Body/);

    fireEvent.click(screen.getByRole('tab', { name: 'Preview' }));

    await waitFor(() => {
      expect(document.querySelector('.prose')?.innerHTML).toBe('<h1>Hi</h1>');
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/posts/7/field/body/preview');
  });
});
