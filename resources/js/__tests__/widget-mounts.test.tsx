import { screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { WidgetDescriptor } from '../widgets/types';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const orders: WidgetDescriptor = {
  name: 'Orders',
  uriKey: 'orders',
  component: 'value-widget',
  width: '1/3',
  columns: 4,
  height: 'fixed',
  help: null,
  helpWidth: 250,
  refreshEvery: 0,
  refreshWhenActionsRun: false,
  refreshWhenFiltersChange: false,
  onlyOnDetail: false,
  requestsValue: true,
  ranges: [],
};

const urlOf = (input: RequestInfo | URL): string => (typeof input === 'string' ? input : input instanceof URL ? input.href : input.url);

/** Answers every value request with 3 and records the widget URLs the page asked for. */
function answer() {
  const fetch = vi.spyOn(globalThis, 'fetch').mockImplementation(() =>
    Promise.resolve(
      new Response(
        JSON.stringify({
          name: 'Orders',
          uriKey: 'orders',
          component: 'value-widget',
          value: { value: 3, previous: null, change: null, previousLabel: null, prefix: null, suffix: null, suffixInflection: true, format: null, allowZeroResult: false, copyable: false },
        }),
        { status: 200 },
      ),
    ),
  );

  return () => fetch.mock.calls.map(([input]) => urlOf(input)).filter((url) => url.includes('/widgets'));
}

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

function renderIndex(widgets: WidgetDescriptor[], lens: { uriKey: string; name: string } | null = null) {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create: false }, definition },
      table: { columns: [], sortKeys: [], rows: [{ id: 1, title: 'ORD-1', cells: {}, abilities: { view: true } }], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: null,
      lens,
      lenses: lens === null ? [] : [lens],
      widgets,
    },
    { url: lens === null ? '/lathe/resources/orders' : `/lathe/resources/orders/lens/${lens.uriKey}` },
  );
}

function card(): HTMLElement {
  const found = document.querySelector<HTMLElement>('[data-lathe-widget="orders"]');

  if (found === null) {
    throw new Error('No card.');
  }

  return found;
}

describe('the cards of a Resource page', () => {
  it('draws the index cards from the widgets prop and asks only for their values', async () => {
    const widgetCalls = answer();
    renderIndex([orders]);

    await waitFor(() => {
      expect(within(card()).getByText('3').tagName).toBe('P');
    });
    expect(widgetCalls()).toEqual(['/lathe/api/resources/orders/widgets/orders']);
  });

  it('asks a lens card for its value under the lens', async () => {
    const widgetCalls = answer();
    renderIndex([orders], { uriKey: 'pending-orders', name: 'Pending Orders' });

    await waitFor(() => {
      expect(within(card()).getByText('3').tagName).toBe('P');
    });
    expect(widgetCalls()).toEqual(['/lathe/api/resources/orders/lenses/pending-orders/widgets/orders']);
  });

  it('asks a detail card for its value with the record id', async () => {
    const widgetCalls = answer();
    renderPage('Resources/Detail', {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } },
      record: { id: 12, title: 'ORD-00012', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
      breadcrumbs: [],
      widgets: [orders],
    });

    await waitFor(() => {
      expect(within(card()).getByText('3').tagName).toBe('P');
    });
    expect(widgetCalls()).toEqual(['/lathe/api/resources/orders/widgets/orders?resourceId=12']);
  });

  it('mounts no grid and asks for nothing when the page has no widgets', async () => {
    const widgetCalls = answer();
    renderIndex([]);

    await screen.findByRole('heading', { level: 1, name: 'Orders' });

    expect(document.querySelector('[data-lathe-widget]')).toBeNull();
    expect(widgetCalls()).toEqual([]);
  });
});
