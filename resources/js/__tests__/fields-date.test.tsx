import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

// Newer ICU puts a narrow no-break space before AM/PM; compare with plain spaces.
const plain = (text: string | null) => (text ?? '').replace(new RegExp('[\\u202f\\u00a0]', 'g'), ' ');

function schema(component: string, overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'when',
    attribute: 'when',
    label: 'When',
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
      <label htmlFor="when">When</label>
      <FormField field={{ ...field, value }} value={value} onChange={onChange} id="when" describedBy={undefined} invalid={false} disabled={false} />
    </>,
  ).container;
}

describe('date-field', () => {
  const field = schema('date-field', { min: '2026-01-01', max: '2026-12-31' });

  it('shows the four values on index, detail and form', () => {
    expect(renderIndex(field, '2026-10-14').textContent).toBe('Oct 14, 2026');
    expect(renderDetail(field, '2026-10-14').textContent).toBe('Oct 14, 2026');
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('edits the Y-m-d text with min and max on the input', () => {
    const onChange = vi.fn();
    renderForm(field, '2026-10-14', onChange);
    const input = screen.getByLabelText<HTMLInputElement>('When');

    expect([input.type, input.value, input.min, input.max]).toEqual(['date', '2026-10-14', '2026-01-01', '2026-12-31']);

    fireEvent.change(input, { target: { value: '2026-11-02' } });
    expect(onChange).toHaveBeenCalledWith('2026-11-02');
  });

  it('draws the int, the string, the boolean and null in the input without throwing', () => {
    expect(renderForm(field, 5).querySelector('input')?.value).toBe('');
    expect(renderForm(field, 'x').querySelector('input')?.value).toBe('');
    expect(renderForm(field, true).querySelector('input')?.value).toBe('');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
  });
});

describe('date-time-field', () => {
  const field = schema('date-time-field', { localValue: '2026-10-14T09:00' });

  it('shows the four values on index, detail and form', () => {
    __panel.sharedProps.set({ locale: 'en', timezone: 'Pacific/Kiritimati' });

    expect(plain(renderIndex(field, '2026-10-13T19:00:00Z').textContent)).toBe('Oct 14, 2026, 9:00 AM');
    expect(plain(renderDetail(field, '2026-10-13T19:00:00Z').textContent)).toBe('Oct 14, 2026, 9:00 AM');
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('shows the server-converted wall time for a UTC value and sends the typed wall time', () => {
    const onChange = vi.fn();
    renderForm({ ...field, min: '2026-01-01T00:00' }, '2026-10-13T19:00:00Z', onChange);
    const input = screen.getByLabelText<HTMLInputElement>('When');

    expect([input.type, input.value, input.min]).toEqual(['datetime-local', '2026-10-14T09:00', '2026-01-01T00:00']);

    fireEvent.change(input, { target: { value: '2026-10-15T10:30' } });
    expect(onChange).toHaveBeenCalledWith('2026-10-15T10:30');
  });

  it('keeps a typed wall time as it is and draws the other values without throwing', () => {
    expect(renderForm(field, '2026-10-15T10:30').querySelector('input')?.value).toBe('2026-10-15T10:30');
    expect(renderForm(field, 5).querySelector('input')?.value).toBe('');
    expect(renderForm(field, true).querySelector('input')?.value).toBe('');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
  });
});

describe('date cell in an index row', () => {
  it('date_cell_click_navigates', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders',
          label: 'Orders',
          singularLabel: 'Order',
          perPageOptions: [25, 50, 100],
          searchable: false,
          abilities: { create: false },
          definition: {
            debounce: 0.5,
            paginationType: 'links',
            clickAction: 'detail',
            polling: null,
            showPollingToggle: false,
            tableStyle: 'default',
            showColumnBorders: false,
            softDeletes: false,
          },
        },
        table: {
          columns: [{ key: 'due_on', label: 'Due on', component: 'date-field', sortable: false, sortKey: 'due_on' }],
          sortKeys: [],
          rows: [{ id: 7, title: 'Order 7', cells: { due_on: { value: '2026-10-14', display: '2026-10-14' } }, abilities: { view: true } }],
          rowKey: 'id',
        },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
        count: { value: 1, exact: true, capped: false, estimated: false },
      },
      { url: '/lathe/resources/orders' },
    );

    const [, body] = screen.getAllByRole('rowgroup');
    fireEvent.click(within(body as HTMLElement).getByText('Oct 14, 2026'));

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/7');
  });
});
