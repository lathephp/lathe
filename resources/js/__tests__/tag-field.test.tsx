import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { FormField, IndexField } from '../fields/registry';
import type { FieldSchema } from '../fields/types';

const tags = [
  { value: 5, display: 'sale' },
  { value: 6, display: 'new' },
  { value: 7, display: 'gift' },
];

function schema(overrides: Partial<FieldSchema> = {}): FieldSchema {
  return {
    key: 'tags',
    attribute: 'tags',
    label: 'Tags',
    component: 'tag-field',
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
    kind: 'tag',
    relationship: 'tags',
    resource: 'tags',
    singularLabel: 'Tag',
    display: 'group',
    withPreview: false,
    preload: false,
    searchable: true,
    debounce: 500,
    ...overrides,
  };
}

function urls(fetch: { mock: { calls: unknown[][] } }): string[] {
  return fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : ''));
}

function Harness({ field, onChange }: { field: FieldSchema; onChange: (value: unknown) => void }) {
  const [value, setValue] = useState<unknown>([]);

  return (
    <>
      <label htmlFor="field-tags">Tags</label>
      <FormField
        field={{ ...field, value }}
        value={value}
        onChange={(next) => {
          onChange(next);
          setValue(next);
        }}
        id="field-tags"
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

beforeEach(() => {
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'posts' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('tag field', () => {
  it('draws the tags of a cell inline as a group and stacked as a list', () => {
    const cell = { value: tags.slice(0, 2), display: 'sale, new' };
    const { unmount } = render(<IndexField field={schema()} cell={cell} />);

    expect(screen.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['sale', 'new']);
    expect(screen.getByRole('list').className).toContain('flex-wrap');
    expect(screen.getByRole('list').className).not.toContain('flex-col');
    unmount();

    render(<IndexField field={schema({ display: 'list' })} cell={cell} />);

    expect(screen.getAllByRole('listitem').map((item) => item.textContent)).toEqual(['sale', 'new']);
    expect(screen.getByRole('list').className).toContain('flex-col');
  });

  it('opens the preview of a tag clicked with withPreview', async () => {
    const fetch = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ id: 6, title: 'new', subtitle: '', fields: [{ key: 'slug', label: 'Slug', component: 'text-field', value: 'new-in', display: 'new-in' }] }), { status: 200 })));
    render(<IndexField field={schema({ withPreview: true })} cell={{ value: tags.slice(0, 2), display: 'sale, new' }} />);

    fireEvent.click(screen.getByRole('button', { name: 'new' }));

    expect(await screen.findByText('new-in')).toBeDefined();
    expect(screen.getByRole('dialog', { name: 'new' })).toBeDefined();
    expect(urls(fetch)).toEqual(['/lathe/api/resources/tags/6/preview']);
  });

  it('lists the preloaded tags without a request and toggles two into the value', () => {
    const fetch = vi.spyOn(globalThis, 'fetch');
    const onChange = vi.fn();
    render(<Harness field={schema({ preload: true, options: tags })} onChange={onChange} />);

    expect(screen.getAllByRole('button').map((button) => button.textContent)).toEqual(['sale', 'new', 'gift']);

    fireEvent.click(screen.getByRole('button', { name: 'sale' }));
    fireEvent.click(screen.getByRole('button', { name: 'gift' }));

    expect(onChange).toHaveBeenLastCalledWith([tags[0], tags[2]]);
    expect(screen.getByRole('button', { name: 'gift' }).getAttribute('aria-pressed')).toBe('true');
    expect(screen.getByRole('button', { name: 'new' }).getAttribute('aria-pressed')).toBe('false');
    expect(fetch).not.toHaveBeenCalled();
  });

  it('searches the server for tags when they are not preloaded', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response(JSON.stringify({ items: tags, has_more: false }), { status: 200 })));
    const onChange = vi.fn();
    render(<Harness field={schema()} onChange={onChange} />);

    expect(fetch).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Tags' }));
    await flush();

    expect(urls(fetch)).toEqual(['/lathe/api/resources/posts/associatable/tags?q=&page=1']);

    fireEvent.click(screen.getByRole('option', { name: 'new' }));

    expect(onChange).toHaveBeenLastCalledWith([{ value: 6, display: 'new' }]);
  });
});
