import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const resource = {
  uriKey: 'orders',
  label: 'Orders',
  singularLabel: 'Order',
  abilities: { create: true },
};

function orderRow(abilities: Record<string, boolean>, trashed = false) {
  return {
    id: 12,
    title: 'ORD-00012',
    cells: { number: { value: 'ORD-00012', display: 'ORD-00012' } },
    abilities,
    trashed,
  };
}

function renderIndex(row: ReturnType<typeof orderRow>) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        ...resource,
        perPageOptions: [25, 50, 100],
        searchable: false,
        abilities: { create: false },
        definition: {
          debounce: 0.5,
          paginationType: 'links',
          clickAction: 'detail',
          polling: null,
          showPollingToggle: false,
          tableStyle: 'default',
          showColumnBorders: false,
          softDeletes: false,
        },
      },
      table: {
        columns: [
          {
            key: 'number',
            label: 'Number',
            component: 'text',
            sortable: false,
            sortKey: 'number',
          },
        ],
        sortKeys: [],
        rows: [row],
        rowKey: 'id',
      },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: 1, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/orders' },
  );
}

function renderDetail(replicate: boolean) {
  renderPage('Resources/Detail', {
    resource,
    breadcrumbs: [],
    record: {
      id: 12,
      title: 'ORD-00012',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [],
      abilities: {
        view: true,
        update: true,
        delete: false,
        replicate,
        restore: false,
        forceDelete: false,
      },
      trashed: false,
    },
  });
}

describe('replicate entries', () => {
  it('links the row menu to the replicate page when the row allows it', () => {
    renderIndex(orderRow({ view: true, replicate: true }));

    fireEvent.click(
      screen.getByRole('button', { name: 'Actions for ORD-00012' }),
    );

    expect(
      screen.getByRole('menuitem', { name: 'Replicate' }).getAttribute('href'),
    ).toBe('/lathe/resources/orders/12/replicate');
  });

  it('leaves Replicate out of the row menu for a row that may not be replicated', () => {
    renderIndex(orderRow({ view: true, replicate: false }));

    fireEvent.click(screen.getByRole('button', { name: 'Actions for ORD-00012' }));

    expect(screen.queryByRole('menuitem', { name: 'Replicate' })).toBeNull();
  });

  it('offers Replicate on the detail page next to Edit when allowed', () => {
    renderDetail(true);

    expect(
      screen.getByRole('link', { name: 'Replicate' }).getAttribute('href'),
    ).toBe('/lathe/resources/orders/12/replicate');
  });

  it('hides Replicate on the detail page when it is denied', () => {
    renderDetail(false);

    expect(screen.queryByRole('link', { name: 'Replicate' })).toBeNull();
  });
});
