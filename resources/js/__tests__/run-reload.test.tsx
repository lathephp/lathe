import { router } from '@inertiajs/react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { RunToastsHost } from '../actions/RunToastsHost';
import { RUN_POLL_MS } from '../actions/RunToasts';
import { runStore } from '../actions/runStore';
import { fakeInertiaServer, renderPage, type FakeRequest } from './support';

const columns = [{ key: 'status', label: 'Status', component: 'text', sortable: false, sortKey: 'status' }];

function rows(status: string, count = 3, first = 1) {
  return Array.from({ length: count }, (_, index) => ({
    id: first + index,
    title: `Order ${String(first + index)}`,
    cells: { status: { value: status, display: status } },
    abilities: { view: true },
  }));
}

const index = (status: string, definition: Record<string, unknown> = {}) => ({
  resource: {
    uriKey: 'orders',
    label: 'Orders',
    singularLabel: 'Order',
    perPageOptions: [25],
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
      ...definition,
    },
  },
  table: { columns, sortKeys: [], rows: rows(status), rowKey: 'id' },
  state: { sort: '', page: 1, perPage: 25, hasMorePages: definition.paginationType === 'load-more' },
  count: { value: 3, exact: true, capped: false, estimated: false },
});

const dashboard = { dashboard: { uriKey: 'main', name: 'Home', description: null, icon: null, showRefreshButton: false }, widgets: [] };

let requests: FakeRequest[];

beforeEach(() => {
  requests = fakeInertiaServer();
  router.flushAll();
  vi.useFakeTimers();
});

afterEach(() => {
  for (const run of runStore.get()) {
    runStore.dismiss(run.kind, run.id);
  }
  vi.useRealTimers();
  vi.unstubAllGlobals();
  vi.restoreAllMocks();
});

const finished = { status: 'finished', total: 3, processed: 3, failed: 0, skipped: 0 };

function startRun() {
  // The panel mounts the host beside the page; renderPage mounts the page alone.
  render(<RunToastsHost />);
  act(() => {
    window.dispatchEvent(new CustomEvent('lathe:bulk-run-started', { detail: { id: '01RUN', total: 3, verb: 'action', resource: 'orders' } }));
  });
  // The toasts are a lazy chunk, loaded by a real import that fake timers do not drive.
  return act(() => vi.dynamicImportSettled());
}

const tick = () => act(() => vi.advanceTimersByTimeAsync(RUN_POLL_MS + 50));
const bodyRows = () => within(screen.getAllByRole('rowgroup')[1] as HTMLElement).queryAllByRole('row');

describe('a bulk run that ends', () => {
  it('reloads the index it started on, in place, once', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(Response.json(finished)));
    renderPage('Resources/Index', index('Pending'), { url: '/lathe/resources/orders' });
    window.history.replaceState(null, '', '/lathe/resources/orders');
    await startRun();
    requests.length = 0;

    await tick();

    expect(requests).toHaveLength(1);
    expect(requests[0]?.headers['X-Inertia-Partial-Data']).toBe('table,count,tabCounts,summaries');
    expect(requests[0]?.headers['X-Inertia-Partial-Component']).toBe('Resources/Index');
    expect(screen.getAllByText('Pending')).toHaveLength(3);
    expect(screen.queryByText(/No .* found|No results/i)).toBeNull();

    await act(async () => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: index('Shipped'), url: '/lathe/resources/orders' });
      await vi.advanceTimersByTimeAsync(10);
    });
    expect(screen.getAllByText('Shipped')).toHaveLength(3);

    await tick();
    await tick();
    expect(requests).toHaveLength(1);
  });

  it('drops the pages a load-more index had accumulated', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(
        (typeof input === 'string' ? input : '').includes('bulk-runs')
          ? Response.json(finished)
          : Response.json({ table: { columns, sortKeys: [], rows: rows('Pending', 2, 4), rowKey: 'id' }, state: { page: 2, perPage: 25, hasMorePages: false, sort: '' } }),
      ),
    );
    renderPage('Resources/Index', index('Pending', { paginationType: 'load-more' }), { url: '/lathe/resources/orders' });
    window.history.replaceState(null, '', '/lathe/resources/orders');
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: 'Load more' }));
      await vi.advanceTimersByTimeAsync(10);
    });
    expect(bodyRows()).toHaveLength(5);
    await startRun();
    requests.length = 0;

    await tick();
    await act(async () => {
      requests[0]?.respond(200, { component: 'Resources/Index', props: index('Shipped', { paginationType: 'load-more' }), url: '/lathe/resources/orders' });
      await vi.advanceTimersByTimeAsync(10);
    });

    expect(bodyRows()).toHaveLength(3);
    expect(screen.getAllByText('Shipped')).toHaveLength(3);
  });

  it('flushes the resource but reloads nothing on another page', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(Response.json(finished)));
    const flush = vi.spyOn(router, 'flushByCacheTags');
    renderPage('Dashboard', dashboard, { url: '/lathe' });
    window.history.replaceState(null, '', '/lathe');
    await startRun();
    requests.length = 0;

    await tick();

    expect(requests).toHaveLength(0);
    expect(flush).toHaveBeenCalledTimes(1);
    expect(flush).toHaveBeenCalledWith('resource:orders');
  });
});
