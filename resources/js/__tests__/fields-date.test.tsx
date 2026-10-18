import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
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
      <label htmlFor="when">{field.label}</label>
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

  it('shows the day as text and round-trips a typed Oct 17, 2026 on Enter', () => {
    const onChange = vi.fn();
    const { rerender } = render(<FormField field={{ ...field, value: '2026-10-14' }} value="2026-10-14" onChange={onChange} id="when" describedBy={undefined} invalid={false} disabled={false} />);
    const input = screen.getByRole<HTMLInputElement>('textbox');

    expect([input.type, input.value]).toEqual(['text', 'Oct 14, 2026']);

    fireEvent.change(input, { target: { value: 'Oct 17, 2026' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    expect(onChange).toHaveBeenCalledWith('2026-10-17');

    rerender(<FormField field={{ ...field, value: '2026-10-17' }} value="2026-10-17" onChange={onChange} id="when" describedBy={undefined} invalid={false} disabled={false} />);
    expect(input.value).toBe('Oct 17, 2026');
  });

  it('keeps an unparseable entry and marks it invalid', () => {
    const onChange = vi.fn();
    renderForm(field, '2026-10-14', onChange);
    const input = screen.getByLabelText<HTMLInputElement>('When');

    fireEvent.change(input, { target: { value: 'Oct 32, 2026' } });
    fireEvent.blur(input);

    expect([input.value, input.getAttribute('aria-invalid')]).toEqual(['Oct 32, 2026', 'true']);
    expect(onChange).not.toHaveBeenCalled();
  });

  it('disables the calendar days outside min and max', async () => {
    renderForm(schema('date-field', { min: '2026-10-10', max: '2026-10-20' }), '2026-10-14');
    fireEvent.click(screen.getByRole('button', { name: 'Open calendar' }));
    const grid = within(await screen.findByRole('grid'));

    expect([/October 9th, 2026/, /October 10th, 2026/, /October 20th, 2026/, /October 21st, 2026/].map((name) => grid.getByRole('button', { name }).hasAttribute('disabled'))).toEqual([true, false, false, true]);
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

    expect(plain(renderIndex(field, '2026-10-13T19:00:00Z').textContent)).toBe('Oct 14, 2026, 09:00');
    expect(plain(renderDetail(field, '2026-10-13T19:00:00Z').textContent)).toBe('Oct 14, 2026, 09:00');
    expect(renderIndex(field, 5).textContent).toBe('5');
    expect(renderIndex(field, 'x').textContent).toBe('x');
    expect(renderDetail(field, true, 'Yes').textContent).toBe('Yes');
    expect(renderIndex(field, null).textContent).toBe('');
    expect(renderDetail(field, null, '').textContent).toBe('—');
  });

  it('shows the server-converted wall time for a UTC value as text', () => {
    renderForm(field, '2026-10-13T19:00:00Z');
    const input = screen.getByLabelText<HTMLInputElement>('When');

    expect([input.type, input.value]).toEqual(['text', 'Oct 14, 2026, 09:00']);
  });

  it('keeps a typed wall time as it is and draws the other values without throwing', () => {
    expect(renderForm(field, '2026-10-15T10:30').querySelector('input')?.value).toBe('Oct 15, 2026, 10:30');
    expect(renderForm(field, 5).querySelector('input')?.value).toBe('');
    expect(renderForm(field, true).querySelector('input')?.value).toBe('');
    expect(renderForm(field, null).querySelector('input')?.value).toBe('');
  });
});

describe('the order form Placed field', () => {
  const placed = schema('date-time-field', { key: 'placed_at', attribute: 'placed_at', label: 'Placed' });

  it('draws no native date or date-time input', () => {
    const form = renderForm(placed, '2026-10-15T10:30');

    expect(form.querySelector('input[type=datetime-local], input[type=date], input[type=time]')).toBeNull();
  });

  it('stores a typed Oct 17, 2026 23:12 as the wall time on blur', () => {
    const onChange = vi.fn();
    renderForm(placed, null, onChange);
    const input = screen.getByLabelText<HTMLInputElement>('Placed');

    fireEvent.change(input, { target: { value: 'Oct 17, 2026 23:12' } });
    fireEvent.blur(input);

    expect(onChange).toHaveBeenCalledWith('2026-10-17T23:12');
  });

  it('opens the calendar from its button and closes it on Escape, back on the button', async () => {
    renderForm(placed, '2026-10-15T10:30');
    const button = screen.getByRole('button', { name: 'Open calendar' });

    // A native button, so Enter and Space click it; jsdom does not turn a keypress into that click.
    expect(button.tagName).toBe('BUTTON');
    button.focus();
    fireEvent.click(button);
    const grid = await screen.findByRole('grid');

    expect(within(grid).getByRole('gridcell', { selected: true }).textContent).toBe('15');

    fireEvent.keyDown(grid, { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('grid')).toBeNull();
    });
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Open calendar' }));
  });

  it('sets a clicked day and keeps the time, and sets a typed time', async () => {
    const onChange = vi.fn();
    renderForm(placed, '2026-10-15T10:30', onChange);
    fireEvent.click(screen.getByRole('button', { name: 'Open calendar' }));

    fireEvent.click(within(await screen.findByRole('grid')).getByRole('button', { name: /October 20th, 2026/ }));
    expect(onChange).toHaveBeenLastCalledWith('2026-10-20T10:30');

    const time = screen.getByLabelText<HTMLInputElement>('Time');
    expect([time.type, time.value]).toEqual(['text', '10:30']);
    fireEvent.change(time, { target: { value: '23:12' } });
    fireEvent.blur(time);
    expect(onChange).toHaveBeenLastCalledWith('2026-10-15T23:12');
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
