import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { ActionMeta } from '../actions/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function meta(uriKey: string, name: string): ActionMeta {
  return {
    uriKey, name, icon: null, description: null, group: null, destructive: false, standalone: false, sole: false, pinned: false,
    withoutConfirmation: true, confirmText: null, confirmButtonText: 'Run Action', cancelButtonText: 'Cancel', size: '2xl', style: 'window',
    asBlob: false, showOnIndex: true, showOnDetail: true, showOnTableRow: true,
  };
}

const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

describe('a row menu\'s inline actions', () => {
  // The server sends each row the actions it may run now, so a run that changes the row changes them.
  it('lists the actions the reloaded row may run, not the ones it could run when they loaded', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
      json({ actions: [meta('hold-order', 'Hold order'), meta('release-order', 'Release order')], pivotActions: [], counts: {} }),
    );
    const row = (actions: string[]) => ({ id: 7, title: 'ORD-7', cells: { number: { value: 'ORD-7', display: 'ORD-7' } }, abilities: { view: true }, actions });
    renderPage(
      'Resources/Index',
      {
        resource: {
          uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false },
          definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false },
        },
        table: { columns: [{ key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' }], sortKeys: [], rows: [row(['hold-order'])], rowKey: 'id' },
        state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      },
      { url: '/lathe/resources/orders' },
    );
    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-7' }));
    await screen.findByRole('menuitem', { name: 'Hold order' });
    fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('menu')).toBeNull();
    });

    act(() => {
      router.replaceProp('table.rows', [row(['release-order'])]);
    });
    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-7' }));
    const menu = await screen.findByRole('menu');

    expect(within(menu).getAllByRole('menuitem').map((item) => item.textContent)).toEqual(['View', 'Release order']);
  });

});
