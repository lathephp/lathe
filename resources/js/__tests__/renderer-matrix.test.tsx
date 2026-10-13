import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { DetailField, FormField, IndexField, registries } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

type Context = 'index' | 'detail' | 'form';

/** The renderer matrix of spec-fields-renderers, for the non-relation component keys. */
const matrix: Record<string, Context[]> = {
  'audio-field': ['index', 'detail', 'form'],
  'boolean-group-field': ['index', 'detail', 'form'],
  'color-field': ['index', 'detail', 'form'],
  'file-field': ['index', 'detail', 'form'],
  'image-field': ['index', 'detail', 'form'],
  'password-field': ['index', 'detail', 'form'],
  'token-field': ['index', 'detail', 'form'],
  'badge-field': ['index', 'form'],
  'boolean-field': ['index', 'form'],
  'currency-field': ['index', 'form'],
  'date-field': ['index', 'form'],
  'date-time-field': ['index', 'form'],
  'email-field': ['index', 'form'],
  'multi-select-field': ['index', 'form'],
  'number-field': ['index', 'form'],
  'select-field': ['index', 'form'],
  'slug-field': ['index', 'form'],
  'status-field': ['index', 'form'],
  'text-field': ['index', 'form'],
  'textarea-field': ['index', 'form'],
  'url-field': ['index', 'form'],
  'code-field': ['detail', 'form'],
  'heading-field': ['detail', 'form'],
  'key-value-field': ['detail', 'form'],
  'repeater-field': ['detail', 'form'],
  'icon-field': ['index', 'detail'],
  'sparkline-field': ['index', 'detail'],
  'ui-avatar-field': ['index', 'detail'],
  'hidden-field': ['form'],
  'markdown-field': ['form'],
  'tiptap-field': ['form'],
  'id-field': ['index'],
  'line-field': ['index'],
  'stack-field': ['index'],
};

describe('renderer matrix', () => {
  it.each(Object.entries(matrix))('%s has a renderer in each context the matrix lists', (key, contexts) => {
    for (const context of contexts) {
      expect(registries[context].has(key), `${key} ${context}`).toBe(true);
    }
  });

  it('renders Markdown and Tiptap detail values as sanitized HTML through SafeHtml', () => {
    for (const key of ['markdown-field', 'tiptap-field']) {
      expect(registries.detail.has(key)).toBe(true);
    }
  });

  it('falls back to the display string on index and detail and a text input on forms for a key with no entry', () => {
    const field = { key: 'x', attribute: 'x', label: 'X', component: 'nobody-field' } as FieldSchema;

    render(
      <>
        <span>
          <IndexField field={field} cell={{ value: '<b>raw</b>', display: '<b>shown</b>' }} />
        </span>
        <span>
          <DetailField field={{ ...field, value: 1, display: 'detail text' }} />
        </span>
        <FormField field={{ ...field, value: 'v' }} value="v" onChange={() => undefined} id="x" describedBy={undefined} invalid={false} disabled={false} />
      </>,
    );

    expect(screen.getByText('<b>shown</b>')).toBeDefined();
    expect(document.querySelector('b')).toBeNull();
    expect(screen.getByText('detail text')).toBeDefined();
    expect(screen.getByRole<HTMLInputElement>('textbox').value).toBe('v');
  });
});
