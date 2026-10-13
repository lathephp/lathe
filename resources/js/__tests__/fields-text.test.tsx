import { __panel, type SafeHtml } from '@lathephp/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { TableCell } from '../table/cells';

afterEach(() => {
  vi.restoreAllMocks();
});

function schema(component: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'name',
    attribute: 'name',
    label: 'Name',
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
    ...overrides,
  };
}

const html = (value: string) => ({ __html: value }) as unknown as SafeHtml;
const display = (value: number | string | boolean | null) => (value === null ? '' : String(value));

const formProps = (field: FieldSchema, value: unknown, onChange = vi.fn()) => ({
  field: { ...field, value },
  value,
  onChange,
  id: 'f',
  describedBy: undefined,
  invalid: false,
  disabled: false,
});

describe('text field components', () => {
  const keys: [string, string][] = [
    ['text-field', 'edited'],
    ['textarea-field', 'edited'],
    ['email-field', 'a@b.co'],
    ['url-field', 'https://x.test'],
    ['color-field', '#ff0000'],
    ['password-field', 'edited'],
  ];

  it.each(keys)('%s renders and edits the values 5, "x", true and null', (component, edited) => {
    for (const value of [5, 'x', true, null]) {
      const field = schema(component);
      const cell = { value, display: display(value) };
      const onChange = vi.fn();

      const index = render(<IndexField field={field} cell={cell} />);
      index.unmount();
      const detail = render(<DetailField field={{ ...field, ...cell }} />);
      detail.unmount();
      const form = render(<FormField {...formProps(field, value, onChange)} />);
      const input = form.container.querySelector('#f');

      expect(input).not.toBeNull();
      fireEvent.change(input as Element, { target: { value: edited } });
      expect(onChange).toHaveBeenCalledWith(edited);
      form.unmount();
    }
  });

  it('id-field shows the key as plain text on the index', () => {
    const { container } = render(<IndexField field={schema('id-field')} cell={{ value: 5, display: '5' }} />);

    expect(container.textContent).toBe('5');
    expect(container.querySelector('a, button, input')).toBeNull();
  });

  it('hidden-field submits its value with no control', () => {
    for (const value of [5, 'x', true, null]) {
      const { container, unmount } = render(<FormField {...formProps(schema('hidden-field', { attribute: 'locale' }), value)} />);
      const input = container.querySelector('input');

      expect(input?.type).toBe('hidden');
      expect(input?.name).toBe('locale');
      expect(input?.value).toBe(value === null ? '' : String(value));
      expect(screen.queryByRole('textbox')).toBeNull();
      unmount();
    }
  });

  it('heading-field renders its sanitized content', () => {
    const field = schema('heading-field', { content: html('<b>Billing</b>') });

    const detail = render(<DetailField field={{ ...field, value: null, display: '' }} />);
    expect(detail.container.querySelector('b')?.textContent).toBe('Billing');
    detail.unmount();

    const form = render(<FormField {...formProps(field, null)} />);
    expect(form.container.querySelector('b')?.textContent).toBe('Billing');
  });

  it('masks a password on forms and on the page', () => {
    const form = render(<FormField {...formProps(schema('password-field'), '')} />);
    expect(form.container.querySelector('input')?.type).toBe('password');
    form.unmount();

    const index = render(<IndexField field={schema('password-field')} cell={{ value: '', display: '' }} />);
    expect(index.container.textContent).toBe('••••••••');
  });

  it('sets the email and url input types', () => {
    const email = render(<FormField {...formProps(schema('email-field', { autocomplete: 'email' }), '')} />);
    const input = email.container.querySelector('input');
    expect(input?.type).toBe('email');
    expect(input?.autocomplete).toBe('email');
    email.unmount();

    const url = render(<FormField {...formProps(schema('url-field', { autocomplete: 'url' }), '')} />);
    expect(url.container.querySelector('input')?.type).toBe('url');
  });

  it('links a url on index and detail, but only for a safe scheme', () => {
    const field = schema('url-field');

    const index = render(<IndexField field={field} cell={{ value: 'https://lathephp.com/', display: 'https://lathephp.com/' }} />);
    expect(index.container.querySelector('a')?.getAttribute('href')).toBe('https://lathephp.com/');
    index.unmount();

    const detail = render(<DetailField field={{ ...field, value: 'https://lathephp.com/', display: 'lathephp.com' }} />);
    expect(detail.container.querySelector('a')?.textContent).toBe('lathephp.com');
    detail.unmount();

    const unsafe = render(<IndexField field={field} cell={{ value: 'javascript:alert(1)', display: 'javascript:alert(1)' }} />);
    expect(unsafe.container.querySelector('a')).toBeNull();
  });

  it('shows at most the suggestions the server sent in a datalist', () => {
    const { container } = render(<FormField {...formProps(schema('text-field', { suggestions: ['a', 'b', 'c'] }), '')} />);

    expect(container.querySelector('input')?.getAttribute('list')).toBe('f-suggestions');
    expect(Array.from(container.querySelectorAll('datalist option')).map((option) => option.getAttribute('value'))).toEqual(['a', 'b', 'c']);
  });

  it('blocks the eleventh character when enforceMaxlength is on', () => {
    const onChange = vi.fn();
    const { container } = render(<FormField {...formProps(schema('text-field', { maxlength: 10, enforceMaxlength: true }), '', onChange)} />);
    const input = container.querySelector('input');

    expect(input?.maxLength).toBe(10);
    fireEvent.change(input as Element, { target: { value: '12345678901' } });
    expect(onChange).toHaveBeenCalledWith('1234567890');
  });

  it('leaves the length to the server without enforceMaxlength', () => {
    const onChange = vi.fn();
    const { container } = render(<FormField {...formProps(schema('text-field', { maxlength: 10 }), '', onChange)} />);

    expect(container.querySelector('input')?.hasAttribute('maxlength')).toBe(false);
    fireEvent.change(container.querySelector('input') as Element, { target: { value: '12345678901' } });
    expect(onChange).toHaveBeenCalledWith('12345678901');
  });

  it('copies on copyable and toasts the outcome', async () => {
    const writeText = vi.fn().mockResolvedValueOnce(undefined).mockRejectedValueOnce(new Error('denied'));
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
    render(<IndexField field={schema('text-field', { copyable: true })} cell={{ value: 'abc', display: 'abc' }} />);

    fireEvent.click(screen.getByRole('button', { name: 'Copy' }));
    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Copied to clipboard']);
    });
    expect(writeText).toHaveBeenCalledWith('abc');

    fireEvent.click(screen.getByRole('button', { name: 'Copy' }));
    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Copied to clipboard', 'Copy failed']);
    });
  });

  it('shows no copy button without copyable', () => {
    render(<IndexField field={schema('text-field')} cell={{ value: 'abc', display: 'abc' }} />);

    expect(screen.queryByRole('button', { name: 'Copy' })).toBeNull();
  });

  describe('collapse', () => {
    const lines = (count: number) => Array.from({ length: count }, (_, index) => `line ${String(index + 1)}`).join('\n');
    const detail = (text: string, overrides: Partial<FieldSchema> = {}) =>
      render(<DetailField field={{ ...schema('textarea-field', overrides), value: text, display: text }} />);

    it('collapses a value over 10 lines behind Show Content and Hide Content', () => {
      detail(lines(11));

      fireEvent.click(screen.getByRole('button', { name: 'Show Content' }));
      expect(screen.getByRole('button', { name: 'Hide Content' }).getAttribute('aria-expanded')).toBe('true');

      fireEvent.click(screen.getByRole('button', { name: 'Hide Content' }));
      expect(screen.getByRole('button', { name: 'Show Content' })).toBeDefined();
    });

    it('keeps 10 lines open', () => {
      detail(lines(10));

      expect(screen.queryByRole('button', { name: 'Show Content' })).toBeNull();
    });

    it('stays open for alwaysShow and for a shouldShow answer of true', () => {
      detail(lines(30), { alwaysShow: true }).unmount();
      expect(screen.queryByRole('button', { name: 'Show Content' })).toBeNull();

      detail(lines(30), { expanded: true });
      expect(screen.queryByRole('button', { name: 'Show Content' })).toBeNull();
    });
  });

  it('renders asHtml as markup and asEncodedHtml as the text', () => {
    const asHtml = render(<IndexField field={schema('text-field', { asHtml: true })} cell={{ value: html('<b>x</b>'), display: 'x' }} />);
    expect(asHtml.container.querySelector('b')?.textContent).toBe('x');
    asHtml.unmount();

    const encoded = render(<IndexField field={schema('text-field', { asEncodedHtml: true })} cell={{ value: '<b>x</b>', display: '<b>x</b>' }} />);
    expect(encoded.container.querySelector('b')).toBeNull();
    expect(encoded.container.textContent).toBe('<b>x</b>');
  });

  it('edits a translatable value per locale', () => {
    const onChange = vi.fn();
    render(<FormField {...formProps(schema('text-field', { translatable: ['en', 'fr'] }), { en: 'a', fr: '' }, onChange)} />);

    fireEvent.change(screen.getByRole('textbox', { name: 'Name (fr)' }), { target: { value: 'b' } });
    expect(onChange).toHaveBeenCalledWith({ en: 'a', fr: 'b' });
    expect(screen.getByRole('textbox', { name: 'Name (en)' }).id).toBe('f');
  });

  it('textarea form takes its rows', () => {
    const { container } = render(<FormField {...formProps(schema('textarea-field', { rows: 6 }), 'x')} />);

    expect(container.querySelector('textarea')?.rows).toBe(6);
  });
});

describe('wrapping', () => {
  const cell = { value: 'long text', display: 'long text' };

  it('wraps an index cell onto at most 5 lines when wrapping is true', () => {
    const { container } = render(<TableCell column={schema('text-field', { wrapping: true })} cell={cell} />);

    expect(container.firstElementChild?.className).toContain('line-clamp-5');
    expect(container.firstElementChild?.getAttribute('data-wrapping')).toBe('true');
  });

  it('shows one line with an ellipsis when wrapping is false', () => {
    const { container } = render(<TableCell column={schema('text-field', { wrapping: false })} cell={cell} />);

    expect(container.firstElementChild?.className).toBe('truncate');
    expect(container.firstElementChild?.hasAttribute('data-wrapping')).toBe(false);
  });
});
