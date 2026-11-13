import { fireEvent, screen, waitFor } from '@testing-library/react';
import { firstCell, panel, press, urlOf } from './preview-support';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cellOf, editCell, indexTable, pick, renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

// The workbench's Pending Orders lens has one inline column, Status; here its Number edits in place too.
function renderLens() {
  const table = Object.assign(indexTable('orders'), { phoneCard: { title: 'number', badge: 'status', meta: null, value: null } });
  table.rows.splice(1);
  table.columns.forEach((column) => {
    if (column.key === 'number') {
      Object.assign(column, { inline: true });
    }
    if ('meter' in column) {
      column.meter = null;
    }
  });

  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { ...table, sortKeys: [] },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: table.rows.length, exact: true, capped: false, estimated: false },
      lens: { uriKey: 'pending-orders', name: 'Pending Orders' },
      lenses: [{ uriKey: 'pending-orders', name: 'Pending Orders' }],
    },
    { url: '/lathe/resources/orders/lens/pending-orders' },
  );
}

const reply = (fields: Record<string, unknown>, updatedAt: string) => new Response(JSON.stringify({ fields, updatedAt }), { status: 200 });

describe('inline edits on a lens', () => {
  it('keeps the first edit of a row when a second cell of it saves', async () => {
    vi.spyOn(globalThis, 'fetch')
      .mockResolvedValueOnce(reply({ number: { value: 'ORD-7', display: 'ORD-7' } }, '2026-01-15T09:30:01.000Z'))
      .mockResolvedValueOnce(reply({ status: { value: 'pending', display: 'Pending' } }, '2026-01-15T09:30:02.000Z'));
    renderLens();

    await editCell('Number');
    const input = await screen.findByRole<HTMLInputElement>('textbox', { name: 'Number' });
    fireEvent.change(input, { target: { value: 'ORD-7' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });
    expect(cellOf('Number').textContent).toContain('ORD-7');

    await editCell('Status');
    await pick('Pending');
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });

    expect(cellOf('Status').textContent).toContain('Pending');
    expect(cellOf('Number').textContent).toContain('ORD-7');
  });

  it('shows both edits of a row in the preview panel header', async () => {
    let saves = 0;
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);

      if (url.includes('/field/')) {
        saves++;

        return Promise.resolve(
          saves === 1 ? reply({ number: { value: 'ORD-7', display: 'ORD-7' } }, '2026-01-15T09:30:01.000Z') : reply({ status: { value: 'pending', display: 'Pending' } }, '2026-01-15T09:30:02.000Z'),
        );
      }

      return Promise.resolve(new Response(null, { status: 204 }));
    });
    renderLens();
    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });

    await editCell('Number');
    const input = await screen.findByRole<HTMLInputElement>('textbox', { name: 'Number' });
    fireEvent.change(input, { target: { value: 'ORD-7' } });
    fireEvent.keyDown(input, { key: 'Enter' });
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });
    await editCell('Status');
    await pick('Pending');
    await waitFor(() => {
      expect(screen.queryByText('Saving…')).toBeNull();
    });

    const header = panel().querySelector('header')?.textContent ?? '';
    expect(header).toContain('ORD-7');
    expect(header).toContain('Pending');
  });
});
