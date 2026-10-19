import type { SafeHtml } from '@lathephp/core';
import type { Editor } from '@tiptap/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, registries } from '../fields/registry';
import { extensions } from '../fields/rich/TiptapEditor';
import type { FieldSchema } from '../fields/types';

afterEach(() => {
  vi.restoreAllMocks();
});

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'body',
    attribute: 'body',
    label: 'Body',
    component: 'tiptap-field',
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

function renderForm(value: unknown, onChange: (value: unknown) => void = () => undefined) {
  return render(<FormField field={{ ...schema(), value }} value={value} onChange={onChange} id="field-body" describedBy={undefined} invalid={false} disabled={false} />);
}

/** The lazy editor once its chunk has loaded. */
async function editorOf() {
  const element = await screen.findByRole('textbox', { name: 'Body' });

  return { element, editor: (element as HTMLElement & { editor: Editor }).editor };
}

describe('tiptap-field', () => {
  it('has a detail and a form renderer and none on the index', () => {
    expect([registries.index.has('tiptap-field'), registries.detail.has('tiptap-field'), registries.form.has('tiptap-field')]).toEqual([false, true, true]);
  });

  it('rich_text_editor_registers_each_extension_once', async () => {
    const warn = vi.spyOn(console, 'warn');
    const seen: { editor: Editor; names: string[] }[] = [];
    const remember = (editor: Editor) => {
      seen.push({ editor, names: editor.extensionManager.extensions.map((extension) => extension.name) });
    };

    const first = renderForm('<p>one</p>');
    remember((await editorOf()).editor);
    first.rerender(<FormField field={{ ...schema(), value: '<p>one</p>' }} value="<p>one</p>" onChange={() => undefined} id="field-body" describedBy={undefined} invalid={false} disabled={false} />);
    remember((await editorOf()).editor);
    first.unmount();
    renderForm('<p>two</p>');
    remember((await editorOf()).editor);

    for (const { editor, names } of seen) {
      expect(names.length).toBe(new Set(names).size);
      expect([names.filter((name) => name === 'link').length, names.filter((name) => name === 'image').length]).toEqual([1, 1]);
      // The module builds its list once and every editor shares it.
      expect(editor.options.extensions).toBe(extensions);
    }
    // A re-render keeps its editor; a new mount makes a new one.
    expect([seen[0]?.editor === seen[1]?.editor, seen[1]?.editor === seen[2]?.editor]).toEqual([true, false]);
    expect(warn.mock.calls.flat().join(' ')).not.toContain('Duplicate extension');
  });

  it('frames the toolbar row and the text in one box, the toolbar on the muted ground', async () => {
    renderForm('<p>one</p>');
    await editorOf();
    const toolbar = screen.getByRole('toolbar', { name: 'Formatting' });

    expect(toolbar.className).toContain('bg-muted');
    expect(toolbar.parentElement?.className).toContain('rounded-md border');
  });

  it('renders the sanitized detail value with the prose class', () => {
    const container = renderDetail(html('<p>Hi</p><img src="x">'), 'Hi');

    expect(container.querySelector('.prose')?.innerHTML).toBe('<p>Hi</p><img src="x">');
    expect(container.querySelector('img')?.getAttribute('onerror')).toBeNull();
    expect(screen.queryByRole('button', { name: 'Show Content' })).toBeNull();
  });

  it('collapses a value over 10 lines behind Show Content', () => {
    const lines = Array.from({ length: 11 }, (_, index) => `line ${String(index + 1)}`);
    renderDetail(html(lines.map((line) => `<p>${line}</p>`).join('')), lines.join('\n'));

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
      const { element } = await editorOf();
      expect([element.id, element.getAttribute('contenteditable'), element.textContent]).toEqual(['field-body', 'true', shown]);
      unmount();
    }

    const onChange = vi.fn();
    renderForm('<p>old</p>', onChange);
    await editorOf();
    fireEvent.click(screen.getByRole('button', { name: 'Horizontal rule' }));

    await waitFor(() => {
      expect(onChange).toHaveBeenLastCalledWith('<hr><p>old</p>');
    });
  });
});
