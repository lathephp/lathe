/// <reference types="node" />
import { screen, waitFor } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { cellOf, indexTable, renderPage } from './support';

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

const labels = { orders: ['Orders', 'Order'], customers: ['Customers', 'Customer'] } as const;

// Orders hold the inline Status with badges; customers hold the inline text Name. The workbench
// Status also draws a meter, which takes the badge's place, so the badge case drops it; the meter
// cases keep it, with or without its badges.
type Shape = { update?: boolean; meter?: boolean; badges?: boolean; boolean?: boolean };

function renderRows(uriKey: 'orders' | 'customers', { update = true, meter = false, badges = true, boolean = false }: Shape = {}) {
  const table = indexTable(uriKey);
  table.columns.forEach((column) => {
    if ('meter' in column && !meter) {
      column.meter = null;
    }
    if ('badges' in column && !badges) {
      column.badges = null;
    }
  });
  table.rows.forEach((row) => {
    row.abilities.update = update;
  });
  // The workbench has no inline boolean, so the customers' Name column is re-typed as one.
  if (boolean) {
    Object.assign(table.columns[0] ?? {}, { component: 'boolean-field', asCheckbox: false, trueValue: true });
    table.rows.forEach((row) => {
      (row.cells as Record<string, unknown>).name = { value: false, display: 'No' };
    });
  }

  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey, label: labels[uriKey][0], singularLabel: labels[uriKey][1], perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { ...table, sortKeys: [] },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: table.rows.length, exact: true, capped: false, estimated: false },
    },
    { url: `/lathe/resources/${uriKey}` },
  );
}

// jsdom has no layout, so the cause is what the test reads: the value and the chevron are siblings
// in one row that centres them on the cross axis.
function expectCentredRow(cell: HTMLElement, value: Element | ChildNode | null | undefined) {
  const chevron = cell.querySelector('[data-inline-chevron]');
  const row = chevron?.parentElement;

  expect(row?.classList.contains('flex')).toBe(true);
  expect(row?.classList.contains('items-center')).toBe(true);
  expect(value?.parentElement).toBe(row);
}

describe('the inline cell row', () => {
  it('centres the chevron beside a boolean icon and text', async () => {
    renderRows('customers', { boolean: true });

    const cell = (await screen.findAllByRole('gridcell', { name: /^Name: .*Change name$/ }))[0] as HTMLElement;

    expect(cell.querySelectorAll('[data-inline-chevron]')).toHaveLength(1);
    expectCentredRow(cell, cell.querySelector('svg:not([data-inline-chevron] svg)')?.parentElement);
  });

  it('centres the chevron beside a plain text value', async () => {
    renderRows('customers');

    const cell = await screen.findByRole('gridcell', { name: 'Name: Grace Hopper. Change name' });
    const row = cell.querySelector('[data-inline-chevron]')?.parentElement;

    expectCentredRow(cell, row?.firstChild);
    expect(row?.firstChild?.textContent).toBe('Grace Hopper');
  });

  it('centres the chevron beside a meter', async () => {
    renderRows('orders', { meter: true });

    const cell = await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });

    expect(cell.querySelectorAll('[data-inline-chevron]')).toHaveLength(1);
    expect(cell.querySelector('[data-lathe-meter] [data-inline-chevron]')).not.toBeNull();
  });

  it('keeps a badge chevron once, inside the badge', async () => {
    renderRows('orders');

    const cell = await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });

    expect(cell.querySelectorAll('[data-inline-chevron]')).toHaveLength(1);
    expect(cell.querySelector('[data-lathe-badge-tone] [data-inline-chevron]')).not.toBeNull();
  });
});

describe('the inline cell chevron', () => {
  it('sits inside a badge value and names the cell', async () => {
    renderRows('orders');

    const cell = await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });
    const chevron = cell.querySelector('[data-lathe-badge-tone] svg[aria-hidden="true"]');

    expect(chevron).not.toBeNull();
    expect(chevron?.closest('[data-inline-chevron]')).not.toBeNull();
  });

  it('sits inside a meter value, as the workbench Status draws it', async () => {
    renderRows('orders', { meter: true });

    const cell = await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });

    expect(cell.querySelector('[data-lathe-meter] [data-inline-chevron] svg[aria-hidden="true"]')).not.toBeNull();
  });

  it('draws once on a meter with no badges', async () => {
    renderRows('orders', { meter: true, badges: false });

    const cell = await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });

    expect(cell.querySelectorAll('[data-inline-chevron]')).toHaveLength(1);
    expect(cell.querySelector('[data-lathe-meter] [data-inline-chevron]')).not.toBeNull();
  });

  it('follows a text value and names the cell', async () => {
    renderRows('customers');

    const cell = await screen.findByRole('gridcell', { name: 'Name: Grace Hopper. Change name' });
    const marker = cell.querySelector('[data-inline-chevron]');

    expect(cell.textContent).toBe('Grace Hopper');
    expect(marker?.querySelector('svg[aria-hidden="true"]')).not.toBeNull();
    expect(marker?.previousSibling?.textContent).toBe('Grace Hopper');
  });

  it('is left off a read-only cell', async () => {
    renderRows('orders');

    await screen.findByRole('gridcell', { name: 'Status: Shipped. Change status' });
    const cell = cellOf('Total');

    expect(cell.textContent).toBe('$499.00');
    expect(cell.querySelector('[data-inline-chevron]')).toBeNull();
    expect(cell.hasAttribute('aria-label')).toBe(false);
  });

  it('is left off every cell of a row the user may not update', async () => {
    renderRows('orders', { update: false });

    await waitFor(() => {
      expect(cellOf('Status').textContent).toBe('Shipped');
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
