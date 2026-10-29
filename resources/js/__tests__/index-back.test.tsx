import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, screen, waitFor } from '@testing-library/react';
import { afterEach, expect, it, vi } from 'vitest';
import { cellOf, editCell, fakeInertiaServer, pick, renderPage } from './support';

// Its own file: Inertia's router and the browser history outlive a test, so a visit made by an
// earlier test in the same file would sit in the history this one steps back through.

afterEach(() => {
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
const options = ['pending', 'paid', 'shipped'].map((value) => ({ value, label: value.charAt(0).toUpperCase() + value.slice(1) }));
// The Orders mix: the title column, a belongsTo link and an inline badge select.
const columns = [
  { key: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number' },
  { key: 'customer', label: 'Customer', component: 'belongs-to-field', sortable: false, sortKey: 'customer' },
  { key: 'status', attribute: 'status', label: 'Status', component: 'select-field', sortable: false, sortKey: 'status', inline: true, options, badges: { pending: 'warning', paid: 'success', shipped: 'info' } },
];

function rows(second: string) {
  return [11, 12].map((id) => {
    const status = id === 12 ? second : 'shipped';

    return {
      id,
      title: `ORD-000${String(id)}`,
      cells: {
        number: { value: `ORD-000${String(id)}`, display: `ORD-000${String(id)}` },
        customer: { value: { value: id, display: `Ann ${String(id)}`, href: `/lathe/resources/customers/${String(id)}` }, display: `Ann ${String(id)}` },
        status: { value: status, display: status },
      },
      abilities: { view: true, update: true },
      updatedAt: '2026-10-14T09:00:00.000Z',
    };
  });
}

const table = (second: string) => ({ columns, rows: rows(second), rowKey: 'id', sortKeys: [] });
const saved = (value: string, updatedAt: string) => new Response(JSON.stringify({ field: 'status', fields: { status: { value, display: value } }, updatedAt }), { status: 200 });
const badge = () => cellOf('Status', 1).querySelector('[data-lathe-badge-tone]')?.textContent;

it("shows a row's current value after Back from its record page, with the cell cursor where it was", async () => {
  const requests = fakeInertiaServer();
  router.flushAll();
  vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(saved('paid', '2026-10-14T09:00:02.000Z')).mockResolvedValueOnce(saved('pending', '2026-10-14T09:00:04.000Z'));
  __panel.toasts.set([]);
  renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: table('pending'),
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: 2, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );

  await editCell('Status', 'Enter', 1);
  await pick('Paid');
  await waitFor(() => {
    expect(__panel.toasts.get()[0]?.action?.label).toBe('Undo');
  });

  // The list's poll brings the row as the save left it.
  act(() => {
    router.reload({ only: ['table'] });
  });
  await waitFor(() => {
    expect(requests).toHaveLength(1);
  });
  await act(async () => {
    requests[0]?.respond(200, { component: 'Resources/Index', props: { table: table('paid') }, url: '/lathe/resources/orders' });
    await Promise.resolve();
  });

  act(() => {
    __panel.toasts.get()[0]?.action?.onClick();
  });
  await waitFor(() => {
    expect(badge()).toBe('Pending');
  });

  act(() => {
    cellOf('Status', 1).focus();
  });
  act(() => {
    router.visit('/lathe/resources/orders/12');
  });
  await waitFor(() => {
    expect(requests).toHaveLength(2);
  });
  await act(async () => {
    requests[1]?.respond(200, { component: 'Dashboard', props: { dashboard: { uriKey: 'main', name: 'ORD-00012', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] }, url: '/lathe/resources/orders/12' });
    await Promise.resolve();
  });
  expect(await screen.findByRole('heading', { name: 'ORD-00012' })).toBeTruthy();

  await act(async () => {
    window.history.back();
    await new Promise((resolve) => setTimeout(resolve, 100));
  });

  expect(window.location.pathname).toBe('/lathe/resources/orders');
  await waitFor(() => {
    expect(requests).toHaveLength(3);
  });
  expect(requests[2]?.headers['X-Inertia-Partial-Data']).toBe('table');
  await act(async () => {
    requests[2]?.respond(200, { component: 'Resources/Index', props: { table: table('pending') }, url: '/lathe/resources/orders' });
    await Promise.resolve();
  });

  await waitFor(() => {
    expect(badge()).toBe('Pending');
  });
  expect(Array.from(screen.getByRole('grid').querySelectorAll('[tabindex="0"]'))).toEqual([cellOf('Status', 1)]);
  expect(document.activeElement).toBe(cellOf('Status', 1));
});
