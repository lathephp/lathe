import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'preview', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

function renderIndex(clickAction: string) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, preview: true, definition: { ...definition, clickAction } },
      table: { columns: [{ key: 'number', label: 'Number', component: 'text' }], rows: [{ id: 12, title: 'ORD-00012', cells: { number: { value: 'ORD-00012', display: 'ORD-00012' } }, abilities: { view: true } }], rowKey: 'id', sortKeys: [] },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );
}

function answerPreview() {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValue(
    new Response(JSON.stringify({ id: 12, title: 'ORD-00012', subtitle: 'Paid', avatar: null, fields: [{ key: 'total', label: 'Total', component: 'text-field', value: '1234.50', display: '$1,234.50' }] }), { status: 200 }),
  );
}

describe('preview modal', () => {
  it('opens from a row click set to preview with one request and no list reload', async () => {
    __panel.sharedProps.set({ ...__panel.sharedProps.get() });
    const fetch = answerPreview();
    const visit = vi.spyOn(router, 'visit');
    const reload = vi.spyOn(router, 'reload');
    renderIndex('preview');

    fireEvent.click(screen.getByText('ORD-00012', { selector: 'td *, td' }));

    expect(await screen.findByText('$1,234.50')).toBeDefined();
    expect(screen.getByRole('dialog', { name: 'ORD-00012' })).toBeDefined();
    expect(fetch.mock.calls.map(([input]) => (typeof input === 'string' ? input : ''))).toEqual(['/lathe/api/resources/orders/12/preview']);
    expect(visit).not.toHaveBeenCalled();
    expect(reload).not.toHaveBeenCalled();
  });

  it('opens from the preview button', async () => {
    const fetch = answerPreview();
    renderIndex('detail');

    fireEvent.click(screen.getByRole('button', { name: 'Preview ORD-00012' }));

    await waitFor(() => {
      expect(screen.getByText('$1,234.50')).toBeDefined();
    });
    expect(fetch).toHaveBeenCalledTimes(1);
    fireEvent.click(screen.getByRole('button', { name: 'Close' }));
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});
