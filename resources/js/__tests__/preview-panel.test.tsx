import { router } from '@inertiajs/react';
import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fakeInertiaServer, renderPage } from './support';

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };
// The workbench Orders mix: the title column, a belongsTo link, a badge select and a plain value.
const columns = [
  { key: 'number', label: 'Number', component: 'text-field', sortable: false, sortKey: 'number' },
  { key: 'customer', label: 'Customer', component: 'belongs-to-field', sortable: false, sortKey: 'customer' },
  { key: 'status', label: 'Status', component: 'select-field', sortable: false, sortKey: 'status', badge: true, options: [{ value: 'pending', label: 'Pending' }, { value: 'paid', label: 'Paid' }] },
  { key: 'total_cents', label: 'Total', component: 'text-field', sortable: false, sortKey: 'total_cents' },
];
const numbers = ['ORD-79194426', 'ORD-79186507', 'ORD-79178588'];

function orders(extra: Record<string, unknown> = {}) {
  return {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', perPageOptions: [25], abilities: { create: false }, definition },
    table: {
      columns,
      rows: numbers.map((number, index) => ({
        id: index + 1,
        title: number,
        cells: {
          number: { value: number, display: number },
          customer: { value: { value: 3, display: 'Gerard Reichert', href: '/lathe/resources/customers/3' }, display: 'Gerard Reichert' },
          status: { value: 'pending', display: 'Pending' },
          total_cents: { value: 16498, display: '$164.98' },
        },
        abilities: { view: true, update: true },
      })),
      rowKey: 'id',
      sortKeys: [],
      phoneCard: { title: 'number', badge: 'status', meta: 'customer', value: 'total_cents' },
      ...extra,
    },
    state: { page: 1, perPage: 25, hasMorePages: false },
  };
}

function reply(id: number) {
  return {
    id,
    title: numbers[id - 1],
    subtitle: '',
    avatar: null,
    fields: [
      { key: 'number', label: 'Number', component: 'text-field', value: numbers[id - 1], display: numbers[id - 1] },
      { key: 'placed_at', label: 'Placed', component: 'text-field', value: '2026-10-18', display: 'Oct 18, 2026' },
    ],
    abilities: { view: true, update: true },
    actions: { actions: [{ uriKey: 'mark-as-shipped', name: 'Mark as shipped', icon: null, description: null, group: null, destructive: false, standalone: false, withoutConfirmation: true, enabled: true }], pivotActions: [], counts: {} },
  };
}

function urlOf(input: RequestInfo | URL): string {
  return typeof input === 'string' ? input : input instanceof URL ? input.href : input.url;
}

/** Answers each preview request with its order, and says which were asked for. */
function previews(): string[] {
  const asked: string[] = [];
  vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
    const url = urlOf(input);
    const match = /\/resources\/orders\/(\d+)\/preview$/.exec(url);

    if (match !== null) {
      asked.push(url);

      return Promise.resolve(new Response(JSON.stringify(reply(Number(match[1]))), { status: 200 }));
    }

    return Promise.resolve(new Response(null, { status: 204 }));
  });

  return asked;
}

beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

async function press(key: string) {
  await waitFor(() => {
    expect(screen.getByRole('grid').hasAttribute('data-grid-ready')).toBe(true);
  });
  fireEvent.keyDown(document.activeElement ?? document.body, { key });
}

function firstCell(): HTMLElement {
  return screen.getByRole('grid').querySelector<HTMLElement>('tbody td[tabindex="0"]') as HTMLElement;
}

function panel(): HTMLElement {
  return document.querySelector<HTMLElement>('[data-lathe-preview]') as HTMLElement;
}

describe('the preview panel', () => {
  it('opens on the cursor row with Space, heads it with the row card at once and puts the id in the URL without a history entry', async () => {
    let answer: (response: Response) => void = () => undefined;
    vi.spyOn(globalThis, 'fetch').mockImplementation(
      (input) =>
        new Promise((resolve) => {
          const url = urlOf(input);

          if (url.endsWith('/preview')) {
            answer = resolve;
          } else {
            resolve(new Response(null, { status: 204 }));
          }
        }),
    );
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const entries = window.history.length;

    firstCell().focus();
    await press(' ');

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    // The header comes from the row, before the preview answers.
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79194426');
    // The badge is the row's own Status cell, drawn as the table draws it.
    const status = screen.getAllByRole('row')[1]?.querySelectorAll('td')[3]?.innerHTML ?? '';
    expect(status).not.toBe('');
    expect(panel().querySelector('header')?.innerHTML).toContain(status);
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });
    expect(window.history.length).toBe(entries);

    act(() => {
      answer(new Response(JSON.stringify(reply(1)), { status: 200 }));
    });
    expect(await within(panel()).findByText('Oct 18, 2026')).toBeTruthy();
  });

  it('lists the reply fields and offers Open full page, Edit and the record actions', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    firstCell().focus();
    await press(' ');
    const aside = await waitFor(() => panel());

    expect(await within(aside).findByText('Placed')).toBeTruthy();
    // Only the fields the endpoint picked: no relation table.
    expect(within(aside).queryByRole('table')).toBeNull();
    expect(within(aside).getByRole('link', { name: 'Open full page' }).getAttribute('href')).toBe('/lathe/resources/orders/1');
    expect(within(aside).getByRole('link', { name: 'Edit' }).getAttribute('href')).toBe('/lathe/resources/orders/1/edit');

    fireEvent.click(await within(aside).findByRole('button', { name: /Actions/ }));
    expect(within(aside).getByRole('menuitem', { name: /Mark as shipped/ })).toBeTruthy();
  });

  it('closes with Space on the same row, and with Close, which hands the focus back to the cursor', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    firstCell().focus();
    await press(' ');
    await waitFor(() => panel());
    await press(' ');
    expect(panel()).toBeNull();

    await press(' ');
    const aside = await waitFor(() => panel());
    fireEvent.click(within(aside).getByRole('button', { name: 'Close' }));

    expect(panel()).toBeNull();
    expect(document.activeElement).toBe(firstCell());
    await waitFor(() => {
      expect(window.location.search).toBe('');
    });
  });

  it('leaves Space on a row checkbox to tick the row', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const box = screen.getAllByRole('checkbox')[1] as HTMLElement;

    box.focus();
    await press(' ');

    expect(panel()).toBeNull();
  });

  it('saves the panel as open, then closed, for this Resource', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(new Response(JSON.stringify(reply(1)), { status: 200 }));
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    firstCell().focus();
    await press(' ');
    await press(' ');

    const saves = fetch.mock.calls.filter(([input]) => urlOf(input).endsWith('/preview-panel'));
    expect(saves.map(([, init]) => [init?.method, init?.body])).toEqual([
      ['PUT', JSON.stringify({ open: true })],
      ['PUT', JSON.stringify({ open: false })],
    ]);
    expect(urlOf(saves[0]?.[0] ?? '')).toBe('/lathe/api/resources/orders/preview-panel');
  });

  it('opens on the first row when the user left this list with the panel open', async () => {
    previews();
    renderPage('Resources/Index', orders({ previewOpen: true }), { url: '/lathe/resources/orders' });

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79194426');
  });
});
