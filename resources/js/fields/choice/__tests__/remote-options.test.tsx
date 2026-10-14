import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { FormField } from '../../registry';
import type { FieldSchema } from '../../types';
import { RemoteChoice } from '../SelectFields';

function schema(component: string, attribute: string): FieldSchema {
  return {
    key: attribute,
    attribute,
    label: 'Roles',
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
    options: [],
    searchable: false,
    displayUsingLabels: false,
    remote: true,
  };
}

function server() {
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = typeof input === 'string' ? input : '';
    const items = url.includes('q=ord') ? [{ value: 'record 1', display: 'record 1' }, { value: 'record 2', display: 'record 2' }] : [{ value: 'item 1', display: 'item 1' }];

    return Promise.resolve(new Response(JSON.stringify({ items, has_more: false }), { status: 200 }));
  });

  return { urls: () => fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : '')) };
}

async function advance(ms: number) {
  await act(async () => {
    vi.advanceTimersByTime(ms);
    for (let index = 0; index < 5; index++) {
      await Promise.resolve();
    }
  });
}

function renderField(component: string, attribute: string, value: unknown) {
  const field = schema(component, attribute);

  return render(<FormField field={{ ...field, value }} value={value} onChange={vi.fn()} id={`field-${attribute}`} describedBy={undefined} invalid={false} disabled={false} />);
}

// The remote search is a lazy chunk; loaded up front, the field renders at once.
beforeAll(async () => {
  await RemoteChoice.preload();
});

beforeEach(() => {
  vi.useFakeTimers();
  __panel.sharedProps.set({ basePath: '/lathe', resource: { uriKey: 'users' }, mode: 'create', id: null });
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
});

describe('remote options', () => {
  it.each([
    ['multi-select-field', 'roles', ['admin'], '/lathe/api/resources/users/associatable/roles'],
    ['select-field', 'role', 'admin', '/lathe/api/resources/users/associatable/role'],
  ])('lets the %s search the server and pick from its items', async (component, attribute, value, endpoint) => {
    const { urls } = server();
    renderField(component, attribute, value);

    expect(urls()).toEqual([]);

    fireEvent.focus(screen.getByRole('searchbox'));
    fireEvent.change(screen.getByRole('searchbox'), { target: { value: 'ord' } });
    await advance(500);

    expect(urls().at(-1)).toBe(`${endpoint}?q=ord&page=1`);

    const shown = screen.getAllByRole('option').map((option) => option.textContent);

    // The saved value shows as itself with no lookup, beside the items the server found.
    expect(shown).toEqual(expect.arrayContaining(['admin', 'record 1', 'record 2']));
    expect(shown).not.toContain('item 1');
  });
});
