/// <reference types="node" />
import { screen, waitFor } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { cellOf, renderPage } from './support';

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

const title = { key: 'title', attribute: 'title', label: 'Title', component: 'text-field', sortable: false, sortKey: 'title' };
const status = {
  key: 'status',
  attribute: 'status',
  label: 'Status',
  component: 'select-field',
  sortable: false,
  sortKey: 'status',
  inline: true,
  badges: { draft: 'neutral' },
  options: [{ value: 'draft', label: 'Draft' }],
};
const name = { key: 'name', attribute: 'name', label: 'Name', component: 'text-field', sortable: false, sortKey: 'name', inline: true };

function renderRows(update = true) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'posts', label: 'Posts', singularLabel: 'Post', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: {
        columns: [title, status, name],
        rows: [
          {
            id: 1,
            title: 'Hello',
            cells: { title: { value: 'Hello', display: 'Hello' }, status: { value: 'draft', display: 'draft' }, name: { value: 'Ada', display: 'Ada' } },
            abilities: { view: true, update },
            updatedAt: '2026-10-14T09:00:00.000Z',
          },
        ],
        rowKey: 'id',
        sortKeys: [],
      },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/posts' },
  );
}

describe('the inline cell chevron', () => {
  it('sits inside a badge value and names the cell', async () => {
    renderRows();

    const cell = await screen.findByRole('gridcell', { name: 'Status: Draft. Change status' });
    const chevron = cell.querySelector('[data-lathe-badge-tone] svg[aria-hidden="true"]');

    expect(chevron).not.toBeNull();
    expect(chevron?.closest('[data-inline-chevron]')).not.toBeNull();
  });

  it('follows a text value and names the cell', async () => {
    renderRows();

    const cell = await screen.findByRole('gridcell', { name: 'Name: Ada. Change name' });
    const marker = cell.querySelector('[data-inline-chevron]');

    expect(cell.textContent).toBe('Ada');
    expect(marker?.querySelector('svg[aria-hidden="true"]')).not.toBeNull();
    expect(marker?.previousSibling?.textContent).toBe('Ada');
  });

  it('is left off a read-only cell', async () => {
    renderRows();

    await screen.findByRole('gridcell', { name: 'Status: Draft. Change status' });
    const cell = cellOf('Title');

    expect(cell.textContent).toBe('Hello');
    expect(cell.querySelector('[data-inline-chevron]')).toBeNull();
    expect(cell.hasAttribute('aria-label')).toBe(false);
  });

  it('is left off every cell of a row the user may not update', async () => {
    renderRows(false);

    await waitFor(() => {
      expect(cellOf('Status').textContent).toBe('Draft');
    });
    expect(document.querySelector('[data-inline-chevron]')).toBeNull();
    expect(cellOf('Status').hasAttribute('aria-label')).toBe(false);
  });
});

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');

function rule(selector: RegExp): string {
  const found = [...css.matchAll(/([^{}]+)\{([^{}]*)\}/g)].find((match) => selector.test((match[1] ?? '').trim()));

  return found?.[2] ?? '';
}

describe('the chevron rules', () => {
  it('hide it until the cell is hovered', () => {
    expect(rule(/^\[data-inline-chevron\]$/)).toMatch(/opacity:\s*0/);
    expect(rule(/td:hover \[data-inline-chevron\]/)).toMatch(/opacity:\s*1/);
  });

  it('show it on the focused cell', () => {
    expect(rule(/td:focus \[data-inline-chevron\]/)).toMatch(/opacity:\s*1/);
  });
});
