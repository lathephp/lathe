import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, registries } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'snippet',
    attribute: 'snippet',
    label: 'Snippet',
    component: 'code-field',
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
    language: 'php',
    height: null,
    fullHeight: false,
    autoHeight: false,
    editorOptions: {},
    ...overrides,
  };
}

function renderDetail(value: unknown, display: string, overrides: Partial<FieldSchema> = {}) {
  return render(<DetailField field={{ ...schema(overrides), value, display }} />).container;
}

async function renderForm(value: unknown, overrides: Partial<FieldSchema> = {}, onChange: (value: unknown) => void = () => undefined) {
  render(<FormField field={{ ...schema(overrides), value }} value={value} onChange={onChange} id="snippet" describedBy={undefined} invalid={false} disabled={false} />);

  return await screen.findByRole<HTMLTextAreaElement>('textbox');
}

function press(textarea: HTMLTextAreaElement, key: string, shiftKey = false) {
  fireEvent.keyDown(textarea, { key, shiftKey });
}

describe('code-field', () => {
  it('has a detail and a form renderer and none on the index', () => {
    expect([registries.index.has('code-field'), registries.detail.has('code-field'), registries.form.has('code-field')]).toEqual([false, true, true]);
  });

  it('shows the detail value as text in a language block, never as HTML', () => {
    const container = renderDetail('<img src=x onerror=alert(1)>', '<img src=x onerror=alert(1)>');

    expect(container.querySelector('pre > code.language-php')?.textContent).toBe('<img src=x onerror=alert(1)>');
    expect(container.querySelector('img')).toBeNull();
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
      const textarea = await renderForm(value);
      expect([textarea.tagName, textarea.value]).toEqual(['TEXTAREA', shown]);
      document.body.innerHTML = '';
    }

    const onChange = vi.fn();
    const textarea = await renderForm('old', {}, onChange);
    fireEvent.change(textarea, { target: { value: 'new' } });

    expect(onChange).toHaveBeenCalledWith('new');
  });

  it('numbers the lines', async () => {
    await renderForm('a\nb\nc');

    expect(screen.getByTestId('code-lines').textContent).toBe('123');
  });

  it('inserts two spaces on Tab and outdents on Shift+Tab', async () => {
    const onChange = vi.fn();
    const textarea = await renderForm('ab', {}, onChange);
    textarea.setSelectionRange(1, 1);
    press(textarea, 'Tab');

    expect(onChange).toHaveBeenLastCalledWith('a  b');

    const outdent = vi.fn();
    document.body.innerHTML = '';
    const again = await renderForm('    x\ny', {}, outdent);
    again.setSelectionRange(5, 5);
    press(again, 'Tab', true);

    expect(outdent).toHaveBeenLastCalledWith('  x\ny');
  });

  it('honours tabSize from editorOptions', async () => {
    const onChange = vi.fn();
    const textarea = await renderForm('ab', { editorOptions: { tabSize: 4 } }, onChange);
    textarea.setSelectionRange(1, 1);
    press(textarea, 'Tab');

    expect(onChange).toHaveBeenLastCalledWith('a    b');
  });

  it('applies height, fullHeight and lineWrapping', async () => {
    const fixed = await renderForm('x', { height: 300 });
    expect(fixed.style.height).toBe('300px');
    document.body.innerHTML = '';

    const full = await renderForm('x', { fullHeight: true });
    expect(full.style.height).toBe('100%');
    document.body.innerHTML = '';

    const wrapped = await renderForm('x', { editorOptions: { lineWrapping: true } });
    expect(wrapped.getAttribute('wrap')).toBe('soft');
    document.body.innerHTML = '';

    const plain = await renderForm('x');
    expect(plain.getAttribute('wrap')).toBe('off');
  });
});
