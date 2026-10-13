import { __panel } from '@lathephp/core';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

afterEach(() => {
  vi.unstubAllGlobals();
});

function schema(component: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'amount',
    attribute: 'amount',
    label: 'Amount',
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
    textAlign: 'right',
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

function display(value: unknown): string {
  return value === null ? '' : typeof value === 'string' ? value : JSON.stringify(value);
}

function renderIndex(field: FieldSchema, value: unknown, shown = display(value)) {
  return render(<IndexField field={field} cell={{ value, display: shown }} />).container;
}

function renderDetail(field: FieldSchema, value: unknown, shown = display(value)) {
  return render(<DetailField field={{ ...field, value, display: shown }} />).container;
}

function renderForm(field: FieldSchema, value: unknown, onChange: (value: unknown) => void = () => undefined) {
  return render(
    <>
      <label htmlFor="amount">Amount</label>
      <FormField field={{ ...field, value }} value={value} onChange={onChange} id="amount" describedBy={undefined} invalid={false} disabled={false} />
    </>,
  ).container;
}

describe('number-field', () => {
  const field = schema('number-field', { min: 0, max: 10, step: 1 });

  it('shows the four values on index, detail and form', () => {
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderDetail(field, 1234.5).textContent).toBe('1,234.5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('puts min, max and step on the input and sends the typed text', () => {
    const onChange = vi.fn();
    renderForm(field, 5, onChange);
    const input = screen.getByLabelText<HTMLInputElement>('Amount');

    expect([input.type, input.value, input.min, input.max, input.step]).toEqual(['number', '5', '0', '10', '1']);

    fireEvent.change(input, { target: { value: '7' } });
    expect(onChange).toHaveBeenCalledWith('7');
  });

  it('draws the string, the boolean and null in the input without throwing', () => {
    expect(renderForm(field, 'x').querySelector('input')?.value).toBe('');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
    expect(renderForm(field, true).querySelector('input')?.value).toBe('');
  });
});

describe('currency-field', () => {
  const field = schema('currency-field', { currency: 'USD', symbol: null, step: '0.01', min: null, max: '1000' });

  it('formats the decimal string on detail and shows the server text on index', () => {
    expect(renderDetail(field, '1234.50').textContent).toBe('$1,234.50');
    expect(renderIndex(field, '1234.50', '$1,234.50').textContent).toBe('$1,234.50');
  });

  it('shows the four values', () => {
    expect(renderDetail(field, 5, '5').textContent).toBe('$5.00');
    expect(renderDetail(field, 'x', 'x').textContent).toBe('x');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderDetail(field, null, '').textContent).toBe('—');
    expect(renderIndex(field, null).textContent).toBe('');
  });

  it('keeps a symbol override instead of the Intl glyph', () => {
    expect(renderDetail({ ...field, symbol: 'US$' }, '12.50', 'US$12.50').textContent).toBe('US$12.50');
  });

  it('edits the decimal string with the currency step', () => {
    const onChange = vi.fn();
    renderForm(field, '12.50', onChange);
    const input = screen.getByLabelText<HTMLInputElement>('Amount');

    expect([input.type, input.value, input.step, input.max]).toEqual(['number', '12.50', '0.01', '1000']);

    fireEvent.change(input, { target: { value: '12.345' } });
    expect(onChange).toHaveBeenCalledWith('12.345');
  });

  it('draws the int, the boolean and null in the input', () => {
    expect(renderForm(field, 5).querySelector('input')?.value).toBe('5');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
  });
});

describe('token-field', () => {
  const field = schema('token-field', { textAlign: 'left' });

  it('shows the masked text on index and detail', () => {
    expect(renderIndex(field, 'abcd********').textContent).toBe('abcd********');
    expect(renderDetail(field, 'abcd********').textContent).toBe('abcd********');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('edits the raw value in a masked input that can be revealed and copied', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    vi.stubGlobal('navigator', { clipboard: { writeText } });
    const onChange = vi.fn();
    renderForm(field, 'abcd1234efgh', onChange);
    const input = screen.getByLabelText<HTMLInputElement>('Amount');

    expect(input.type).toBe('password');
    expect(input.value).toBe('abcd1234efgh');

    fireEvent.click(screen.getByRole('button', { name: 'Show' }));
    expect(input.type).toBe('text');
    fireEvent.click(screen.getByRole('button', { name: 'Hide' }));
    expect(input.type).toBe('password');

    fireEvent.click(screen.getByRole('button', { name: 'Copy' }));
    expect(writeText).toHaveBeenCalledWith('abcd1234efgh');
    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['Copied']);
    });

    fireEvent.change(input, { target: { value: 'zzzz' } });
    expect(onChange).toHaveBeenCalledWith('zzzz');
  });

  it('draws the int, the string and null', () => {
    expect(renderForm(field, 5).querySelector('input')?.value).toBe('5');
    expect(renderForm(field, 'x').querySelector('input')?.value).toBe('x');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
  });
});
