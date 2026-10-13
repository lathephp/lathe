import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { registries, type FormFieldProps } from '../fields/registry';
import type { DetailFieldData, FieldSchema, FormFieldData } from '../fields/types';
import { renderPage } from './support';

function schema(component: string, extra: Record<string, unknown> = {}): FieldSchema {
  return {
    key: 'thing',
    attribute: 'thing',
    label: 'Thing',
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
    ...extra,
  };
}

function renderIndex(component: string, cell: Record<string, unknown> & { value: unknown }, extra: Record<string, unknown> = {}) {
  const Index = registries.index.get(component);

  if (Index === undefined) {
    throw new Error(`No index renderer for ${component}.`);
  }

  return render(<Index field={schema(component, extra)} cell={{ display: '', ...cell }} />);
}

function renderDetail(component: string, value: unknown, extra: Record<string, unknown> = {}) {
  const Detail = registries.detail.get(component);

  if (Detail === undefined) {
    throw new Error(`No detail renderer for ${component}.`);
  }

  const field: DetailFieldData = { ...schema(component, extra), value, display: '' };

  return render(<Detail field={field} />);
}

function renderForm(component: string, value: unknown, onChange: (value: unknown) => void = vi.fn()) {
  const Form = registries.form.get(component);

  if (Form === undefined) {
    throw new Error(`No form renderer for ${component}.`);
  }

  const field: FormFieldData = { ...schema(component), value };
  const props: FormFieldProps = { field, value, onChange, id: 'thing', describedBy: undefined, invalid: false, disabled: false };

  return render(<Form {...props} />);
}

const fourValues: unknown[] = [5, 'x', true, null];

describe('renderer matrix', () => {
  it('registers exactly the contexts the matrix lists', () => {
    const contexts = (key: string) => [registries.index.has(key) && 'index', registries.detail.has(key) && 'detail', registries.form.has(key) && 'form'].filter(Boolean);

    expect(contexts('badge-field')).toEqual(['index', 'form']);
    expect(contexts('status-field')).toEqual(['index', 'form']);
    expect(contexts('icon-field')).toEqual(['index', 'detail']);
    expect(contexts('sparkline-field')).toEqual(['index', 'detail']);
    expect(contexts('stack-field')).toEqual(['index']);
    expect(contexts('line-field')).toEqual(['index']);
  });
});

describe('badge-field', () => {
  it('draws the label with its type, and a custom type through data-lathe-badge-type', () => {
    renderIndex('badge-field', { value: 'gold', display: 'Gold', type: 'vip' });
    renderIndex('badge-field', { value: 'paid', display: 'Paid', type: 'success', icon: 'circle-check' });

    expect(screen.getByText('Gold').getAttribute('data-lathe-badge-type')).toBe('vip');
    expect(screen.getByText('Paid').getAttribute('data-lathe-badge-type')).toBe('success');
    expect(screen.getByText('Paid').querySelector('svg')).not.toBeNull();
    expect(screen.getByText('Gold').querySelector('svg')).toBeNull();
  });

  it('edits the form value as text', () => {
    const onChange = vi.fn();
    renderForm('badge-field', 'paid', onChange);

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'pending' } });

    expect(onChange).toHaveBeenCalledWith('pending');
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderIndex('badge-field', { value, display: String(value), type: 'info' });
    renderForm('badge-field', value);

    expect(screen.getByRole('textbox')).toBeTruthy();
  });
});

describe('status-field', () => {
  it('draws a spinner while loading and a plain badge when finished', () => {
    renderIndex('status-field', { value: 'run', display: 'Run', type: 'loading' });
    renderIndex('status-field', { value: 'done', display: 'Done', type: 'success' });

    expect(screen.getByText('Run').getAttribute('data-lathe-badge-type')).toBe('loading');
    expect(screen.getByText('Run').querySelector('svg')?.getAttribute('class')).toContain('animate-spin');
    expect(screen.getByText('Done').getAttribute('data-lathe-badge-type')).toBe('success');
  });

  it('edits the form value as text', () => {
    const onChange = vi.fn();
    renderForm('status-field', 'done', onChange);

    fireEvent.change(screen.getByRole('textbox'), { target: { value: 'failed' } });

    expect(onChange).toHaveBeenCalledWith('failed');
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderIndex('status-field', { value, display: String(value), type: 'success' });
    renderForm('status-field', value);

    expect(screen.getByRole('textbox')).toBeTruthy();
  });
});

describe('icon-field', () => {
  it('draws the mapped icon in its color token, and nothing without an icon', () => {
    renderIndex('icon-field', { value: 'a', display: 'a', icon: 'star', color: 'warning' });
    renderDetail('icon-field', 'zzz');

    const icon = screen.getByRole('img', { name: 'a' });

    expect(icon.getAttribute('data-lathe-icon')).toBe('star');
    expect(icon.className).toContain('text-amber-600');
    expect(icon.querySelector('svg')).not.toBeNull();
  });

  it.each(fourValues)('renders the value %s in index and detail', (value) => {
    const { container } = renderIndex('icon-field', { value, display: String(value), icon: 'circle', color: null });
    renderDetail('icon-field', value);

    expect(container.querySelector('svg')).not.toBeNull();
  });
});

describe('sparkline-field', () => {
  it('draws a polyline of the series in a 100 by 50 box', () => {
    const { container } = renderIndex('sparkline-field', { value: [1, 3, 2] }, { height: 50, width: 100, asBarChart: false });
    const svg = container.querySelector('svg');

    expect(svg?.getAttribute('width')).toBe('100');
    expect(svg?.getAttribute('height')).toBe('50');
    expect(container.querySelector('polyline')?.getAttribute('points')).toBe('2,48 50,2 98,25');
  });

  it('draws one bar per value as a bar chart', () => {
    const { container } = renderIndex('sparkline-field', { value: [1, 2, 4] }, { height: 20, width: 60, asBarChart: true });

    expect(container.querySelectorAll('rect')).toHaveLength(3);
    expect(container.querySelector('svg')?.getAttribute('width')).toBe('60');
  });

  it.each(fourValues)('renders the value %s in index and detail', (value) => {
    const index = renderIndex('sparkline-field', { value });
    const detail = renderDetail('sparkline-field', value);

    expect(index.container.querySelector('svg')).toBeNull();
    expect(detail.container.textContent).toBe('—');
  });
});

describe('stack-field and line-field', () => {
  it('draws each line with its style and extra classes as given', () => {
    renderIndex('stack-field', {
      value: [
        { display: 'Ada', style: 'heading', extraClasses: '' },
        { display: 'ada@example.com', style: 'small', extraClasses: 'text-red-500 host-class' },
      ],
    });

    expect(screen.getByText('Ada').className).toContain('font-semibold');
    expect(screen.getByText('ada@example.com').className).toContain('text-xs');
    expect(screen.getByText('ada@example.com').className).toContain('text-red-500 host-class');
  });

  it('draws a single line from its cell', () => {
    renderIndex('line-field', { value: 'x', display: 'x', style: 'subtitle', extraClasses: 'mine' });

    expect(screen.getByText('x').className).toContain('text-muted-foreground');
    expect(screen.getByText('x').className).toContain('mine');
  });

  it.each(fourValues)('renders the value %s', (value) => {
    const stack = renderIndex('stack-field', { value });
    const line = renderIndex('line-field', { value, display: value === null ? '' : String(value) });

    expect(stack.container.textContent).toBe('');
    expect(line.container.textContent).toBe(value === null ? '' : String(value));
  });
});

describe('column alignment', () => {
  it('aligns a right column header and cells to the end', () => {
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders',
          label: 'Orders',
          singularLabel: 'Order',
          perPageOptions: [25],
          searchable: false,
          abilities: { create: false },
          definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
        },
        table: {
          columns: [
            { key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number', textAlign: 'left' },
            { key: 'total', label: 'Total', component: 'currency-field', sortable: false, sortKey: 'total', textAlign: 'right' },
          ],
          sortKeys: [],
          rows: [{ id: 1, title: 'ORD-1', cells: { number: { value: 'ORD-1', display: 'ORD-1' }, total: { value: '1234.50', display: '$1,234.50' } }, abilities: { view: true } }],
          rowKey: 'id',
        },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
        count: { value: 1, exact: true, capped: false, estimated: false },
      },
      { url: '/lathe/resources/orders' },
    );

    expect(screen.getByRole('columnheader', { name: 'Total' }).className).toContain('text-end');
    expect(screen.getByText('$1,234.50').closest('td')?.className).toContain('text-end');
    expect(screen.getByRole('columnheader', { name: 'Number' }).className).not.toContain('text-end');
    expect(screen.getByText('ORD-1').closest('td')?.className).not.toContain('text-end');
  });
});
