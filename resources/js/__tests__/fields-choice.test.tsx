import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { registries, type FormFieldProps } from '../fields/registry';
import type { DetailFieldData, FieldSchema, FormFieldData } from '../fields/types';
import { choose, optionLabels } from './support';

const options = [
  { value: 'a', label: 'Alpha' },
  { value: 'b', label: 'Beta' },
  { value: 'c', label: 'Gamma' },
];

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
    options,
    searchable: false,
    displayUsingLabels: false,
    ...extra,
  };
}

function formProps(component: string, value: unknown, extra: Record<string, unknown> = {}, onChange: (value: unknown) => void = vi.fn()): FormFieldProps {
  const field: FormFieldData = { ...schema(component, extra), value };

  return { field, value, onChange, id: 'thing', describedBy: undefined, invalid: false, disabled: false };
}

function renderForm(component: string, value: unknown, extra: Record<string, unknown> = {}, onChange: (value: unknown) => void = vi.fn()) {
  const Form = registries.form.get(component);

  if (Form === undefined) {
    throw new Error(`No form renderer for ${component}.`);
  }

  return render(<Form {...formProps(component, value, extra, onChange)} />);
}

function renderIndex(component: string, value: unknown, extra: Record<string, unknown> = {}) {
  const Index = registries.index.get(component);

  if (Index === undefined) {
    throw new Error(`No index renderer for ${component}.`);
  }

  return render(<Index field={schema(component, extra)} cell={{ value, display: '' }} />);
}

function renderDetail(component: string, value: unknown, extra: Record<string, unknown> = {}) {
  const Detail = registries.detail.get(component);

  if (Detail === undefined) {
    throw new Error(`No detail renderer for ${component}.`);
  }

  const field: DetailFieldData = { ...schema(component, extra), value, display: '' };

  return render(<Detail field={field} />);
}

const fourValues: unknown[] = [5, 'x', true, null];

describe('select-field', () => {
  const statuses = [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'cancelled', label: 'Cancelled' },
  ];

  it('opens a list of the four statuses and reports the one picked', async () => {
    const onChange = vi.fn();
    const { container } = renderForm('select-field', 'pending', { options: statuses }, onChange);
    const trigger = screen.getByRole('combobox');

    expect(container.querySelector('select')).toBeNull();
    expect(trigger.textContent).toBe('Pending');
    expect(await optionLabels(trigger)).toEqual(['', 'Pending', 'Paid', 'Shipped', 'Cancelled']);

    await choose(screen.getByRole('combobox'), 'Shipped');
    expect(onChange).toHaveBeenCalledWith('shipped');
  });

  it('reports null when the empty option is picked', async () => {
    const onChange = vi.fn();
    renderForm('select-field', 'b', {}, onChange);

    expect(screen.getByRole('combobox').textContent).toBe('Beta');

    await choose(screen.getByRole('combobox'), '');
    expect(onChange).toHaveBeenCalledWith(null);
  });

  it('opens from the focused trigger with Enter and closes with Escape', async () => {
    // A fresh module, so the list has not loaded and the trigger is the first-paint one.
    vi.resetModules();
    const { SelectForm: Fresh } = await import('../fields/choice/SelectForms');
    render(<Fresh {...formProps('select-field', 'pending', { options: statuses })} />);
    const trigger = screen.getByRole('combobox');

    trigger.focus();
    fireEvent.keyDown(trigger, { key: 'Enter' });
    const list = await screen.findByRole('listbox');

    fireEvent.keyDown(list, { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
  });

  it('opens on the default country with its name selected', () => {
    renderForm('select-field', 'US', { options: [{ value: 'BD', label: 'Bangladesh' }, { value: 'US', label: 'United States' }] });

    expect(screen.getByRole('combobox').textContent).toBe('United States');
  });

  it('filters the options with the search box and keeps the selected one', async () => {
    renderForm('select-field', 'c', { searchable: true });

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'alp' } });

    expect(await optionLabels(screen.getByRole('combobox'))).toEqual(['', 'Alpha', 'Gamma']);
  });

  it('groups options by their group', async () => {
    renderForm('select-field', null, {
      options: [
        { value: 'n', label: 'None' },
        { value: 'a', label: 'Apple', group: 'Fruit' },
      ],
    });

    fireEvent.mouseDown(screen.getByRole('combobox'), { button: 0 });
    await screen.findByRole('listbox');

    const groups = screen.getAllByRole('group');
    expect(groups).toHaveLength(1);
    expect(groups[0]?.textContent).toBe('FruitApple');
  });

  it('draws the label on index and detail only with displayUsingLabels', () => {
    renderIndex('select-field', 'b', { displayUsingLabels: true });
    renderDetail('select-field', 'b', { displayUsingLabels: true });
    renderIndex('select-field', 'b');

    expect(screen.getAllByText('Beta')).toHaveLength(2);
    expect(screen.getByText('b')).toBeTruthy();
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderForm('select-field', value);
    renderIndex('select-field', value);
    renderDetail('select-field', value);

    expect(screen.getByRole('combobox')).toBeTruthy();
  });
});

describe('boolean-field', () => {
  it('flips a switch', () => {
    const onChange = vi.fn();
    renderForm('boolean-field', false, {}, onChange);

    const toggle = screen.getByRole('switch');
    expect(toggle.getAttribute('aria-checked')).toBe('false');

    fireEvent.click(toggle);
    expect(onChange).toHaveBeenCalledWith(true);
  });

  it('swaps the switch for a checkbox with asCheckbox', () => {
    const onChange = vi.fn();
    renderForm('boolean-field', true, { asCheckbox: true }, onChange);

    expect(screen.queryByRole('switch')).toBeNull();
    fireEvent.click(screen.getByRole('checkbox'));
    expect(onChange).toHaveBeenCalledWith(false);
  });

  it('cycles a nullable boolean through null, true and false', () => {
    const onChange = vi.fn();
    const { unmount } = renderForm('boolean-field', null, { nullable: true }, onChange);
    fireEvent.click(screen.getByRole('switch'));
    unmount();
    const second = renderForm('boolean-field', true, { nullable: true }, onChange);
    fireEvent.click(screen.getByRole('switch'));
    second.unmount();
    renderForm('boolean-field', false, { nullable: true }, onChange);
    fireEvent.click(screen.getByRole('switch'));

    expect(onChange.mock.calls).toEqual([[true], [false], [null]]);
  });

  it('shows Yes, No or nothing on index and detail', () => {
    renderIndex('boolean-field', true);
    renderDetail('boolean-field', false);
    renderIndex('boolean-field', null);

    expect(screen.getByText('Yes')).toBeTruthy();
    expect(screen.getByText('No')).toBeTruthy();
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderForm('boolean-field', value);
    renderIndex('boolean-field', value);
    renderDetail('boolean-field', value);

    expect(screen.getByRole('switch').getAttribute('aria-checked')).toBe(String(value === true));
  });
});

describe('boolean-group-field', () => {
  const group = { options: [{ value: 'email', label: 'Email' }, { value: 'sms', label: 'SMS' }] };

  it('reports the whole object when one box changes', () => {
    const onChange = vi.fn();
    renderForm('boolean-group-field', { email: true, sms: false }, group, onChange);

    expect(screen.getByRole<HTMLInputElement>('checkbox', { name: 'Email' }).checked).toBe(true);
    fireEvent.click(screen.getByRole('checkbox', { name: 'SMS' }));

    expect(onChange).toHaveBeenCalledWith({ email: true, sms: true });
  });

  it('hides the false values, then the true values, and falls back to the no-value text', () => {
    const value = { email: true, sms: false };
    const first = renderIndex('boolean-group-field', value, { ...group, hideFalseValues: true });

    expect(screen.getByText('Email')).toBeTruthy();
    expect(screen.queryByText('SMS')).toBeNull();
    first.unmount();

    const second = renderIndex('boolean-group-field', value, { ...group, hideTrueValues: true });
    expect(screen.queryByText('Email')).toBeNull();
    expect(screen.getByText('SMS')).toBeTruthy();
    second.unmount();

    renderIndex('boolean-group-field', {}, { ...group, noValueText: 'No Data' });
    expect(screen.getByText('No Data')).toBeTruthy();
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderForm('boolean-group-field', value, group);
    renderIndex('boolean-group-field', value, { ...group, noValueText: 'No Data' });
    renderDetail('boolean-group-field', value, { ...group, noValueText: 'No Data' });

    expect(screen.getAllByRole('checkbox')).toHaveLength(2);
    expect(screen.getAllByText('No Data')).toHaveLength(2);
  });
});

describe('multi-select-field', () => {
  function Harness({ initial, extra = {} }: { initial: unknown; extra?: Record<string, unknown> }) {
    const [value, setValue] = useState<unknown>(initial);
    const Form = registries.form.get('multi-select-field');

    if (Form === undefined) {
      throw new Error('No form renderer.');
    }

    return <Form {...formProps('multi-select-field', value, extra, setValue)} />;
  }

  it('lists the four statuses and reports a pick with the ones already chosen', async () => {
    const onChange = vi.fn();
    const statuses = [
      { value: 'pending', label: 'Pending' },
      { value: 'paid', label: 'Paid' },
      { value: 'shipped', label: 'Shipped' },
      { value: 'cancelled', label: 'Cancelled' },
    ];
    const { container } = renderForm('multi-select-field', ['pending'], { options: statuses }, onChange);

    expect(container.querySelector('select')).toBeNull();
    expect(await optionLabels(screen.getByRole('combobox'))).toEqual(['Pending', 'Paid', 'Shipped', 'Cancelled']);

    fireEvent.mouseDown(screen.getByRole('combobox'), { button: 0 });
    const shipped = await screen.findByRole('option', { name: 'Shipped' });
    await waitFor(() => {
      fireEvent.click(shipped);
      expect(onChange).toHaveBeenCalledWith(['pending', 'shipped']);
    });
  });

  it('opens from the focused trigger with Enter and closes with Escape', async () => {
    // A fresh module, so the list has not loaded and the trigger is the first-paint one.
    vi.resetModules();
    const { MultiSelectForm: Fresh } = await import('../fields/choice/SelectForms');
    render(<Fresh {...formProps('multi-select-field', ['a'])} />);
    const trigger = screen.getByRole('combobox');

    trigger.focus();
    fireEvent.keyDown(trigger, { key: 'Enter' });
    const list = await screen.findByRole('listbox');

    fireEvent.keyDown(list, { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
  });

  it('multi_select_keeps_selection', () => {
    render(<Harness initial={['a', 'b', 'c']} extra={{ withSelectedTags: true }} />);

    fireEvent.click(screen.getByRole('button', { name: 'Remove Beta' }));

    expect(screen.getByRole('combobox').textContent).toBe('Alpha, Gamma');
    expect(screen.getByRole('button', { name: 'Remove Alpha' })).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Remove Gamma' })).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Remove Beta' })).toBeNull();
  });

  it('keeps a selected item while the search hides it', async () => {
    const onChange = vi.fn();
    renderForm('multi-select-field', ['c'], { searchable: true }, onChange);

    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'alp' } });
    fireEvent.mouseDown(screen.getByRole('combobox'), { button: 0 });
    const alpha = await screen.findByRole('option', { name: 'Alpha' });
    expect(screen.queryByRole('option', { name: 'Beta' })).toBeNull();

    await waitFor(() => {
      fireEvent.click(alpha);
      expect(onChange).toHaveBeenCalledWith(['c', 'a']);
    });
  });

  it('shows no tags with withSelectedTags false', () => {
    render(<Harness initial={['a']} extra={{ withSelectedTags: false }} />);

    expect(screen.queryByRole('button', { name: 'Remove Alpha' })).toBeNull();
  });

  it('draws the selected items as tags on index and detail', () => {
    renderIndex('multi-select-field', ['a', 'c']);
    renderDetail('multi-select-field', ['b']);

    expect(screen.getAllByText(/^(Alpha|Gamma|Beta)$/)).toHaveLength(3);
  });

  it.each(fourValues)('renders the value %s in every context', (value) => {
    renderForm('multi-select-field', value);
    renderIndex('multi-select-field', value);
    renderDetail('multi-select-field', value);

    expect(screen.getByRole('combobox')).toBeTruthy();
  });
});
