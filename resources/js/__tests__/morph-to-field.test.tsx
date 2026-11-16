import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { registerRelationFields } from '../fields/builtin/relation';
import { FormField } from '../fields/registry';
import { choose, optionLabels } from './support';
import type { FieldSchema } from '../fields/types';

registerRelationFields();

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'commentable',
    attribute: 'commentable',
    label: 'Commentable',
    component: 'morph-to-field',
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
    kind: 'morphTo',
    relationship: 'commentable',
    types: [
      { value: 'posts', label: 'Post' },
      { value: 'products', label: 'Product' },
    ],
    defaultType: null,
    searchable: true,
    debounce: 500,
    withTrashed: true,
    ...overrides,
  };
}

function urls(fetch: { mock: { calls: unknown[][] } }): string[] {
  return fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : ''));
}

function server() {
  return vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ items: [{ value: 7, display: 'Hello world' }], has_more: false }), { status: 200 })));
}

function Harness({ field, onChange, initial = null }: { field: FieldSchema; onChange: (value: unknown) => void; initial?: unknown }) {
  const [value, setValue] = useState<unknown>(initial);

  return (
    <>
      <label htmlFor="field-commentable">Commentable</label>
      <FormField
        field={{ ...field, value }}
        value={value}
        onChange={(next) => {
          onChange(next);
          setValue(next);
        }}
        id="field-commentable"
        describedBy={undefined}
        invalid={false}
        disabled={false}
      />
    </>
  );
}

async function flush() {
  await act(async () => {
    for (let index = 0; index < 5; index++) {
      await Promise.resolve();
    }
  });
}

/** The record picker's search input, or null before a type is chosen. */
function picker(): HTMLInputElement {
  return document.getElementById('field-commentable-record') as HTMLInputElement;
}

// Base UI opens the list on the press and mounts it a frame later.
async function openPicker() {
  fireEvent.mouseDown(picker());
  await waitFor(() => {
    expect(document.querySelector('[data-slot="combobox-list"]')).not.toBeNull();
  });
  await flush();
}

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'comments' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('morphTo form field', () => {
  it('asks for the type first and sends nothing until a type is chosen and the picker opened', async () => {
    const fetch = server();
    render(<Harness field={schema()} onChange={() => undefined} />);

    const type = screen.getByLabelText('Commentable');
    expect(await optionLabels(type)).toEqual(['—', 'Post', 'Product']);
    expect(type.textContent).toBe('—');
    expect(picker()).toBeNull();

    await choose(screen.getByLabelText('Commentable'), 'Post');
    await flush();
    expect(fetch).not.toHaveBeenCalled();

    expect(picker().placeholder).toBe('Select post');
    await openPicker();

    expect(urls(fetch)).toEqual(['/lathe/api/resources/comments/associatable/commentable?type=posts&q=&page=1']);
  });

  it('picks a record of the chosen type, and a new type clears it and looks up that type', async () => {
    const fetch = server();
    const onChange = vi.fn();
    render(<Harness field={schema()} onChange={onChange} />);

    await choose(screen.getByLabelText('Commentable'), 'Post');
    await openPicker();
    fireEvent.click(screen.getByRole('option', { name: 'Hello world' }));

    expect(onChange).toHaveBeenLastCalledWith({ type: 'posts', id: 7, display: 'Hello world' });
    expect(picker().value).toBe('Hello world');

    // The open list hid the type select from the accessibility tree; it shows again once the list is shut.
    await waitFor(() => {
      expect(screen.queryByRole('listbox')).toBeNull();
    });
    await choose(screen.getByLabelText('Commentable'), 'Product');

    expect(onChange).toHaveBeenLastCalledWith({ type: 'products', id: null });
    expect(picker().placeholder).toBe('Select product');
    await openPicker();

    expect(urls(fetch).at(-1)).toBe('/lathe/api/resources/comments/associatable/commentable?type=products&q=&page=1');
  });

  it('shows the current record and its type on the edit form, and asks the record route', async () => {
    __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'comments' }, mode: 'update', id: 5 });
    const fetch = server();
    render(<Harness field={schema()} onChange={() => undefined} initial={{ type: 'products', id: 3, display: 'Desk lamp', href: '/lathe/resources/products/3' }} />);

    expect(screen.getByLabelText('Commentable').textContent).toBe('Product');
    expect(picker().value).toBe('Desk lamp');
    await openPicker();

    expect(urls(fetch)).toEqual(['/lathe/api/resources/comments/5/commentable/associatable?type=products&q=&page=1']);
  });

  it('preselects the default type on the create form', () => {
    server();
    render(<Harness field={schema({ defaultType: 'posts' })} onChange={() => undefined} />);

    expect(screen.getByLabelText('Commentable').textContent).toBe('Post');
    expect(picker().placeholder).toBe('Select post');
  });

  it('leaves out With trashed when the field turns it off', async () => {
    server();
    render(<Harness field={schema({ withTrashed: false, defaultType: 'posts' })} onChange={() => undefined} />);

    await openPicker();

    expect(screen.getByRole('listbox')).toBeDefined();
    expect(screen.queryByRole('checkbox', { name: 'With trashed' })).toBeNull();
  });
});
