import { fireEvent, render, screen, within } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FormField, registries, type FormFieldProps } from '../fields/registry';
import { RepeaterErrorsContext } from '../fields/repeater/errors';
import type { DetailFieldData, FieldSchema } from '../fields/types';

function sub(attribute: string, label: string, extra: Record<string, unknown> = {}): FieldSchema {
  return schema('text-field', { key: attribute, attribute, label, ...extra });
}

function schema(component: string, extra: Record<string, unknown> = {}): FieldSchema {
  return {
    key: 'specifications',
    attribute: 'specifications',
    label: 'Specifications',
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

function repeater(extra: Record<string, unknown> = {}): FieldSchema {
  return schema('repeater-field', {
    mode: 'fields',
    fields: [sub('name', 'Name'), sub('value', 'Value')],
    blocks: [],
    reorderable: true,
    addable: true,
    deletable: true,
    collapsible: false,
    collapsed: false,
    cloneable: false,
    itemNumbers: false,
    confirmRemoval: false,
    grid: null,
    addButtonText: null,
    rowLabel: null,
    min: null,
    max: null,
    ...extra,
  });
}

function Harness({ field, initial, onChange }: { field: FieldSchema; initial: unknown; onChange: (value: unknown) => void }) {
  const [value, setValue] = useState(initial);
  const props: FormFieldProps = {
    field: { ...field, value: initial },
    value,
    onChange: (next) => {
      setValue(next);
      onChange(next);
    },
    id: 'f',
    describedBy: undefined,
    invalid: false,
    disabled: false,
  };

  return <FormField {...props} />;
}

function renderRepeater(field: FieldSchema, initial: unknown, errors: Record<string, string> = {}) {
  const onChange = vi.fn();
  const view = render(
    <RepeaterErrorsContext value={errors}>
      <Harness field={field} initial={initial} onChange={onChange} />
    </RepeaterErrorsContext>,
  );

  return { ...view, onChange, rows: () => view.container.querySelectorAll('[data-repeater-row]') };
}

const two = [
  { name: 'Color', value: 'Red' },
  { name: 'Size', value: 'L' },
];

describe('repeater-field', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders and edits the values 5, "x", true and null', () => {
    const Detail = registries.detail.get('repeater-field');

    expect(registries.index.get('repeater-field')).toBeUndefined();
    expect(registries.form.get('repeater-field')).toBeDefined();

    for (const value of [5, 'x', true, null]) {
      const field: DetailFieldData = { ...repeater(), value, display: '' };
      const detail = render(Detail === undefined ? null : <Detail field={field} />);

      expect(detail.container.textContent).toBe('—');
      detail.unmount();

      const form = renderRepeater(repeater(), value);

      expect(form.rows()).toHaveLength(0);
      fireEvent.click(screen.getByRole('button', { name: 'Add row' }));
      expect(form.onChange).toHaveBeenCalledWith([{ name: null, value: null }]);
      form.unmount();
    }
  });

  it('adds a row with the add button text and edits a sub-field in its row', () => {
    const view = renderRepeater(repeater({ addButtonText: 'Add spec' }), two);

    fireEvent.click(screen.getByRole('button', { name: 'Add spec' }));
    expect(view.rows()).toHaveLength(3);

    const third = view.rows()[2] as HTMLElement;
    fireEvent.change(within(third).getByLabelText('Name'), { target: { value: 'Weight' } });

    expect(view.onChange).toHaveBeenLastCalledWith([...two, { name: 'Weight', value: null }]);
  });

  it('moves a row with the keyboard buttons and with drag and drop', () => {
    const view = renderRepeater(repeater(), two);

    fireEvent.click(within(view.rows()[0] as HTMLElement).getByRole('button', { name: 'Move down' }));
    expect(view.onChange).toHaveBeenLastCalledWith([two[1], two[0]]);

    const [first, second] = [...view.rows()] as HTMLElement[];
    const handle = within(second as HTMLElement).getByRole('button', { name: 'Drag to reorder' });
    fireEvent.dragStart(handle, { dataTransfer: { setData: vi.fn(), effectAllowed: 'none' } });
    fireEvent.dragOver(first as HTMLElement);
    fireEvent.drop(first as HTMLElement);

    expect(view.onChange).toHaveBeenLastCalledWith(two);
  });

  it('starts collapsed with collapsed() and expands a row on click', () => {
    const view = renderRepeater(repeater({ collapsible: true, collapsed: true }), two);

    expect(screen.queryByLabelText('Name')).toBeNull();

    fireEvent.click(within(view.rows()[0] as HTMLElement).getByRole('button', { name: 'Expand' }));

    expect(screen.getAllByLabelText('Name')).toHaveLength(1);
    expect(within(view.rows()[0] as HTMLElement).getByRole('button', { name: 'Collapse' }).getAttribute('aria-expanded')).toBe('true');
  });

  it('clones a row to the end of the list', () => {
    const view = renderRepeater(repeater({ cloneable: true }), [{ name: 'Color', value: 'Red', _label: 'computed' }]);

    fireEvent.click(screen.getByRole('button', { name: 'Clone' }));

    expect(view.rows()).toHaveLength(2);
    expect(view.onChange).toHaveBeenLastCalledWith([
      { name: 'Color', value: 'Red', _label: 'computed' },
      { name: 'Color', value: 'Red' },
    ]);
  });

  it('asks before removing a row, and Escape keeps the row', () => {
    const view = renderRepeater(repeater({ confirmRemoval: true }), two);

    fireEvent.click(within(view.rows()[0] as HTMLElement).getByRole('button', { name: 'Remove' }));
    expect(screen.getByRole('dialog')).not.toBeNull();

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(view.rows()).toHaveLength(2);
    expect(view.onChange).not.toHaveBeenCalled();

    fireEvent.click(within(view.rows()[0] as HTMLElement).getByRole('button', { name: 'Remove' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Remove' }));

    expect(view.rows()).toHaveLength(1);
    expect(view.onChange).toHaveBeenLastCalledWith([two[1]]);
  });

  it('removes at once without confirmRemoval', () => {
    const view = renderRepeater(repeater(), two);

    fireEvent.click(within(view.rows()[1] as HTMLElement).getByRole('button', { name: 'Remove' }));

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(view.onChange).toHaveBeenLastCalledWith([two[0]]);
  });

  it('drops the handle, the add button and the delete button when turned off', () => {
    const view = renderRepeater(repeater({ reorderable: false, addable: false, deletable: false }), two);

    expect(screen.queryByRole('button', { name: 'Drag to reorder' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Move down' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Add row' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Remove' })).toBeNull();
    expect(view.rows()).toHaveLength(2);
  });

  it('numbers rows, lays them out on a grid and in the compact layout', () => {
    const view = renderRepeater(repeater({ itemNumbers: true, grid: 3, compact: true }), two);
    const headings = [...view.container.querySelectorAll('[data-repeater-row] [data-row-title]')].map((node) => node.textContent);

    expect(headings).toEqual(['1. Row #1', '2. Row #2']);
    expect(view.container.querySelector('[data-repeater-row] [data-row-body]')?.className).toContain('md:grid-cols-3');
    expect(view.container.querySelector('[data-repeater-rows]')?.getAttribute('data-compact')).toBe('true');
  });

  it('titles a row with the live sub-field, the template or Row #N, and a server label', () => {
    const view = renderRepeater(repeater({ rowLabel: { field: 'name', fallback: 'Spec #{{index}}' } }), [{ name: 'Color', value: 'Red' }, { name: '' }]);
    const titles = () => [...view.container.querySelectorAll('[data-row-title]')].map((node) => node.textContent);

    expect(titles()).toEqual(['Color', 'Spec #2']);

    fireEvent.change(within(view.rows()[0] as HTMLElement).getByLabelText('Name'), { target: { value: 'Colour' } });
    expect(titles()).toEqual(['Colour', 'Spec #2']);
    view.unmount();

    const plain = renderRepeater(repeater({ rowLabel: { field: 'name', fallback: null } }), [{ name: null }]);
    expect(plain.container.querySelector('[data-row-title]')?.textContent).toBe('Row #1');
    plain.unmount();

    const computed = renderRepeater(repeater({ rowLabel: { field: null, fallback: null } }), [{ name: 'a', _label: 'From the server' }]);
    expect(computed.container.querySelector('[data-row-title]')?.textContent).toBe('From the server');
  });

  it('expands a failing collapsed row, shows its message and scrolls it into view', () => {
    const scroll = vi.fn();
    Element.prototype.scrollIntoView = scroll;
    const view = renderRepeater(repeater({ collapsible: true, collapsed: true }), two, { 'specifications.1.name': 'The name field is required.' });

    const failing = view.rows()[1] as HTMLElement;

    expect(within(failing).getByLabelText('Name').getAttribute('aria-invalid')).toBe('true');
    expect(within(failing).getByText('The name field is required.')).not.toBeNull();
    expect(within(view.rows()[0] as HTMLElement).queryByLabelText('Name')).toBeNull();
    expect(scroll).toHaveBeenCalledTimes(1);
    expect(scroll.mock.contexts[0]).toBe(failing);
  });

  it('adds a polymorphic row of the chosen block and draws only its fields', () => {
    const view = renderRepeater(
      repeater({
        mode: 'repeatables',
        fields: [],
        blocks: [
          { key: 'text-block', label: 'Text Block', icon: null, confirmRemoval: false, fields: [sub('body', 'Body')] },
          { key: 'image', label: 'Image', icon: null, confirmRemoval: true, fields: [sub('url', 'Url')] },
        ],
      }),
      [{ type: 'text-block', body: 'Hello' }],
    );

    fireEvent.click(screen.getByRole('button', { name: 'Add Image' }));

    expect(view.onChange).toHaveBeenLastCalledWith([{ type: 'text-block', body: 'Hello' }, { type: 'image', url: null }]);
    expect(within(view.rows()[1] as HTMLElement).getByLabelText('Url')).not.toBeNull();
    expect(within(view.rows()[1] as HTMLElement).queryByLabelText('Body')).toBeNull();

    fireEvent.click(within(view.rows()[1] as HTMLElement).getByRole('button', { name: 'Remove' }));
    expect(screen.getByRole('dialog')).not.toBeNull();
  });

  it('edits a simple repeater as a flat list', () => {
    const view = renderRepeater(repeater({ mode: 'simple', fields: [sub('tag', 'Tag')] }), ['a']);

    fireEvent.click(screen.getByRole('button', { name: 'Add row' }));
    fireEvent.change(within(view.rows()[1] as HTMLElement).getByLabelText('Tag'), { target: { value: 'b' } });

    expect(view.onChange).toHaveBeenLastCalledWith(['a', 'b']);
  });

  it('lists the rows and their sub-field values on the detail page', () => {
    const Detail = registries.detail.get('repeater-field');
    const field: DetailFieldData = { ...repeater({ itemNumbers: true }), value: two, display: '' };
    const { container } = render(Detail === undefined ? null : <Detail field={field} />);
    const rows = [...container.querySelectorAll('[data-repeater-row]')].map((node) => node.textContent);

    expect(rows).toEqual(['1. Row #1NameColorValueRed', '2. Row #2NameSizeValueL']);
  });
});
