import { render, fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { DetailField, IndexField, registries } from '../fields/registry';
import type { FieldSchema } from '../fields/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'title',
    attribute: 'title',
    label: 'Title',
    component: 'text-field',
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

const slugField = (overrides: Partial<FieldSchema> = {}, value: unknown = '') =>
  ({ ...schema({ key: 'slug', attribute: 'slug', label: 'Slug', component: 'slug-field', from: 'title', separator: '-', showCustomizeButton: false, follow: true, locked: false, ...overrides }), value });

const pairsField = (overrides: Partial<FieldSchema> = {}, value: unknown = null) =>
  ({
    ...schema({
      key: 'meta',
      attribute: 'meta',
      label: 'Meta',
      component: 'key-value-field',
      keyLabel: 'Key',
      valueLabel: 'Value',
      actionText: 'Add row',
      disableEditingKeys: false,
      disableAddingRows: false,
      disableDeletingRows: false,
      noValueText: 'No Data',
      ...overrides,
    }),
    value,
  });

function renderForm(fields: ReturnType<typeof slugField>[], mode: 'create' | 'update' = 'create') {
  return renderPage('Resources/Form', {
    resource: { uriKey: 'posts', label: 'Posts', singularLabel: 'Post', abilities: { create: true } },
    mode,
    id: mode === 'update' ? 7 : null,
    title: 'Create Post',
    breadcrumbs: [],
    form: {
      fields,
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: fields.map((field) => field.key), toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
    },
    submitLabel: 'Create Post',
  });
}

const title = () => ({ ...schema(), value: '' });
const slugInput = () => screen.getByLabelText<HTMLInputElement>(/Slug/);

describe('slug-field', () => {
  it('has an index and a form renderer', () => {
    expect([registries.index.has('slug-field'), registries.form.has('slug-field')]).toEqual([true, true]);
  });

  it('follows the title until the user types in it', () => {
    renderForm([title(), slugField()]);

    fireEvent.change(screen.getByLabelText(/Title/), { target: { value: 'Hello World' } });
    expect(slugInput().value).toBe('hello-world');

    fireEvent.change(slugInput(), { target: { value: 'my-own' } });
    fireEvent.change(screen.getByLabelText(/Title/), { target: { value: 'Another Title' } });
    expect(slugInput().value).toBe('my-own');
  });

  it('joins the words with the separator', () => {
    renderForm([title(), slugField({ separator: '_' })]);

    fireEvent.change(screen.getByLabelText(/Title/), { target: { value: 'Hello World' } });

    expect(slugInput().value).toBe('hello_world');
  });

  it('locks an update form behind a Customize button that unlocks it', () => {
    renderForm([title(), slugField({ showCustomizeButton: true, follow: false, locked: true }, 'hello-world')], 'update');

    expect(slugInput().disabled).toBe(true);
    fireEvent.click(screen.getByRole('button', { name: 'Customize' }));

    expect(slugInput().disabled).toBe(false);
    expect(screen.queryByRole('button', { name: 'Customize' })).toBeNull();
  });

  it('edits freely on update without the button and never follows the title', () => {
    renderForm([title(), slugField({ follow: false }, 'hello-world')], 'update');

    expect(slugInput().disabled).toBe(false);
    expect(screen.queryByRole('button', { name: 'Customize' })).toBeNull();
    fireEvent.change(screen.getByLabelText(/Title/), { target: { value: 'Changed' } });
    expect(slugInput().value).toBe('hello-world');
  });

  it('shows the four values on the index and in the form', () => {
    for (const [value, shown] of [
      [5, '5'],
      ['x', 'x'],
      [true, 'true'],
      [null, ''],
    ] as const) {
      const { unmount } = renderForm([slugField({}, value)]);
      expect(slugInput().value).toBe(shown);
      unmount();
    }

    const { container } = render(<IndexField field={schema({ component: 'slug-field' })} cell={{ value: 'x', display: 'x' }} />);
    expect(container.textContent).toBe('x');

    renderForm([slugField({}, 'old')]);
    fireEvent.change(slugInput(), { target: { value: 'new' } });
    expect(slugInput().value).toBe('new');
  });
});

describe('key-value-field', () => {
  it('has a detail and a form renderer and none on the index', () => {
    expect([registries.index.has('key-value-field'), registries.detail.has('key-value-field'), registries.form.has('key-value-field')]).toEqual([false, true, true]);
  });

  it('lists the pairs on the detail page and the no-value text when empty', () => {
    const { container, unmount } = render(<DetailField field={{ ...pairsField({}, { a: '1', b: '2' }), display: '' }} />);
    expect([...container.querySelectorAll('tr')].map((row) => row.textContent)).toEqual(['KeyValue', 'a1', 'b2']);
    unmount();

    expect(render(<DetailField field={{ ...pairsField({ noValueText: 'Nothing here' }), display: '' }} />).container.textContent).toBe('Nothing here');
  });

  it('adds, edits and removes rows in the form', () => {
    renderForm([pairsField({}, { a: '1' })]);

    expect(screen.getByLabelText<HTMLInputElement>('Key 1').value).toBe('a');
    fireEvent.click(screen.getByRole('button', { name: 'Add row' }));
    fireEvent.change(screen.getByLabelText('Key 2'), { target: { value: 'b' } });
    fireEvent.change(screen.getByLabelText('Value 2'), { target: { value: '2' } });
    expect([screen.getByLabelText<HTMLInputElement>('Key 2').value, screen.getByLabelText<HTMLInputElement>('Value 2').value]).toEqual(['b', '2']);

    fireEvent.click(screen.getByRole('button', { name: 'Remove row 1' }));
    expect(screen.getByLabelText<HTMLInputElement>('Key 1').value).toBe('b');
    expect(screen.queryByLabelText('Key 2')).toBeNull();
  });

  it('locks the keys, the add button and the remove buttons from the payload', () => {
    renderForm([pairsField({ disableEditingKeys: true, disableAddingRows: true, disableDeletingRows: true }, { a: '1' })]);

    expect(screen.getByLabelText<HTMLInputElement>('Key 1').disabled).toBe(true);
    expect(screen.getByLabelText<HTMLInputElement>('Value 1').disabled).toBe(false);
    expect(screen.queryByRole('button', { name: 'Add row' })).toBeNull();
    expect(screen.queryByRole('button', { name: 'Remove row 1' })).toBeNull();
  });

  it('uses the labels and action text of the payload', () => {
    renderForm([pairsField({ keyLabel: 'Header', valueLabel: 'Content', actionText: 'Add header' }, { a: '1' })]);

    expect(screen.getByText('Header')).toBeDefined();
    expect(screen.getByText('Content')).toBeDefined();
    expect(screen.getByRole('button', { name: 'Add header' })).toBeDefined();
  });

  it('shows the four values on the detail page and in the form', () => {
    for (const value of [5, 'x', true, null]) {
      const { container, unmount } = render(<DetailField field={{ ...pairsField({}, value), display: '' }} />);
      expect(container.textContent).toBe('No Data');
      unmount();
      const form = renderForm([pairsField({}, value)]);
      expect(screen.queryByLabelText('Key 1')).toBeNull();
      form.unmount();
    }
  });
});
