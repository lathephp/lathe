import { router } from '@inertiajs/react';
import { __panel, QueryClientProvider, queryClient } from '@lathephp/core';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SearchPalette } from '../palette/SearchPalette';
import { renderPage } from './support';

const recentsKey = 'lathe-recent-searches';
const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] };

function row(resource: string, id: number, title: string, subTitle: string | null = null, avatar: string | null = null) {
  return {
    resourceTitle: resource === 'customers' ? 'Customers' : 'Orders',
    title,
    subTitle,
    resourceName: resource,
    resourceId: id,
    avatar,
    rounded: avatar !== null,
    url: `/resources/${resource}/${String(id)}`,
  };
}

const found = {
  groups: [
    {
      resourceName: 'customers',
      label: 'Customers',
      results: [
        row('customers', 12, 'Ann Taylor', 'ann@example.com'),
        row('customers', 14, 'Annette Fox', 'annette@example.com', 'https://cdn.test/annette.png'),
      ],
    },
    { resourceName: 'orders', label: 'Orders', results: [row('orders', 7, 'ORD-0007', 'Ann Taylor')] },
  ],
  skipped: [],
};

interface Call {
  url: string;
  signal: AbortSignal;
}

let calls: Call[] = [];

/** Answer every search with `body`, as JSON with `status`. */
function answer(body: unknown, status = 200) {
  calls = [];
  vi.stubGlobal(
    'fetch',
    vi.fn((url: string, init: RequestInit) => {
      calls.push({ url, signal: init.signal as AbortSignal });

      // A plain object, so the body settles on microtasks the fake clock can drive.
      return Promise.resolve({ status, ok: status < 400, text: () => Promise.resolve(JSON.stringify(body)) });
    }),
  );
}

/** Never answer; reject when the request is aborted, as fetch does. */
function hang() {
  calls = [];
  vi.stubGlobal(
    'fetch',
    vi.fn(
      (url: string, init: RequestInit) =>
        new Promise((_resolve, reject) => {
          calls.push({ url, signal: init.signal as AbortSignal });
          init.signal?.addEventListener('abort', () => {
            reject(new DOMException('Aborted', 'AbortError'));
          });
        }),
    ),
  );
}

// The query client notifies on a 0 ms timeout, so the clock moves once more after the delay.
async function wait(ms: number) {
  await act(async () => {
    await vi.advanceTimersByTimeAsync(ms);
    await vi.advanceTimersByTimeAsync(0);
  });
}

function box(): HTMLInputElement {
  return screen.getByRole<HTMLInputElement>('combobox');
}

async function typeTerm(term: string) {
  fireEvent.change(box(), { target: { value: term } });
  await wait(500);
  // The answer settles on microtasks, then the client notifies on a timeout.
  await wait(10);
}

function renderPalette(onClose = vi.fn()) {
  const view = render(
    <QueryClientProvider client={queryClient}>
      <SearchPalette basePath="/lathe" debounce={0.5} onClose={onClose} />
    </QueryClientProvider>,
  );

  return { onClose, ...view };
}

beforeEach(() => {
  // jsdom does not scroll, so it has no scrollIntoView.
  Element.prototype.scrollIntoView = vi.fn();
  vi.useFakeTimers();
  queryClient.clear();
  localStorage.clear();
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

describe('typing', () => {
  it('waits 500 ms, trims the term and asks once', async () => {
    answer(found);
    renderPalette();

    fireEvent.change(box(), { target: { value: '  ann ' } });
    await wait(499);

    expect(calls).toHaveLength(0);

    await wait(1);

    expect(calls.map((call) => call.url)).toEqual(['/lathe/api/search?q=ann']);
  });

  it('cancels the request in flight when the term changes', async () => {
    hang();
    renderPalette();

    await typeTerm('ann');

    expect(calls[0]?.signal.aborted).toBe(false);

    await typeTerm('anna');

    expect(calls.map((call) => call.url)).toEqual(['/lathe/api/search?q=ann', '/lathe/api/search?q=anna']);
    expect(calls[0]?.signal.aborted).toBe(true);
    expect(calls[1]?.signal.aborted).toBe(false);
  });

  it('sends one request for the same term typed again within 60 s', async () => {
    answer(found);
    const first = renderPalette();

    await typeTerm('ann');
    first.unmount();
    await wait(40_000);
    renderPalette();
    await typeTerm('ann');

    expect(calls).toHaveLength(1);
    expect(screen.getByText('Annette Fox')).toBeTruthy();
  });
});

describe('states', () => {
  it('shows 3 skeleton rows while searching', async () => {
    hang();
    renderPalette();

    await typeTerm('ann');

    expect(document.querySelectorAll('[data-skeleton]')).toHaveLength(3);
  });

  it('says what it did not find', async () => {
    answer({ groups: [], skipped: [] });
    renderPalette();

    await typeTerm('warrne');

    expect(screen.getByText('No results for "warrne"')).toBeTruthy();
  });

  it('shows a failure and retries it', async () => {
    answer({ message: 'Server Error' }, 500);
    renderPalette();

    await typeTerm('ann');

    expect(screen.getByText('Search failed')).toBeTruthy();

    answer(found);
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));
    await wait(0);

    expect(screen.queryByText('Search failed')).toBeNull();
    expect(screen.getByText('Annette Fox')).toBeTruthy();
  });

  it('warns when a Resource was skipped', async () => {
    answer({ ...found, skipped: ['Users'] });
    renderPalette();

    await typeTerm('ann');

    expect(screen.getByText('Some results are missing')).toBeTruthy();
  });
});

describe('results', () => {
  it('groups rows under their Resource label with avatar, title and subtitle', async () => {
    answer(found);
    renderPalette();

    await typeTerm('ann');

    const groups = screen.getAllByRole('group');

    expect(groups.map((group) => group.getAttribute('aria-label'))).toEqual(['Customers', 'Orders']);

    const customers = within(groups[0] as HTMLElement).getAllByRole('option');

    expect(customers).toHaveLength(2);
    expect(within(customers[0] as HTMLElement).getByText('AT')).toBeTruthy();
    expect(within(customers[0] as HTMLElement).getByText('ann@example.com')).toBeTruthy();
    expect(customers[1]?.querySelector('img')?.getAttribute('src')).toBe('https://cdn.test/annette.png');
    expect(within(within(groups[1] as HTMLElement).getByRole('option')).getByText('OR')).toBeTruthy();
  });

  it('shows an order with its customer, date and total, and links to all 20 orders', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const orders = [1049, 1656, 1839].map((id) => row('orders', id, `ORD-${String(id)}`, 'Prof. Warren Shields II · Oct 17, 2026 · $1,234.00'));

    answer({
      groups: [
        { resourceName: 'customers', label: 'Customers', results: [row('customers', 270, 'Prof. Warren Shields II')], count: null },
        { resourceName: 'orders', label: 'Orders', results: orders, count: { value: 20, exact: false, capped: false, estimated: false } },
      ],
      skipped: [],
    });
    const { onClose } = renderPalette();

    await typeTerm('warren');

    const group = screen.getByRole('group', { name: 'Orders' });

    expect(within(within(group).getAllByRole('option')[0] as HTMLElement).getByText('Prof. Warren Shields II · Oct 17, 2026 · $1,234.00')).toBeTruthy();
    expect(screen.queryByRole('link', { name: /Show all .* customers/ })).toBeNull();

    const all = within(group).getByRole('link', { name: 'Show all 20 orders' });

    expect(all.getAttribute('href')).toBe('/lathe/resources/orders?q=warren');

    fireEvent.click(all);

    expect(visit).toHaveBeenCalledWith('/lathe/resources/orders?q=warren');
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('wires the input to the listbox and the active option', async () => {
    answer(found);
    renderPalette();

    await typeTerm('ann');

    const options = screen.getAllByRole('option');

    expect(screen.getByRole('dialog').getAttribute('aria-modal')).toBe('true');
    expect(box().getAttribute('aria-controls')).toBe(screen.getByRole('listbox').id);
    expect(box().getAttribute('aria-activedescendant')).toBe(options[0]?.id);
    expect(options[0]?.getAttribute('aria-selected')).toBe('true');
  });
});

describe('keyboard', () => {
  async function ready() {
    answer(found);
    const view = renderPalette();

    await typeTerm('ann');

    return view;
  }

  function active(): string {
    return document.getElementById(box().getAttribute('aria-activedescendant') ?? '')?.textContent ?? '';
  }

  it('moves with the arrows, Home and End', async () => {
    await ready();

    expect(active()).toContain('Ann Taylor');

    fireEvent.keyDown(box(), { key: 'ArrowDown' });
    expect(active()).toContain('Annette Fox');

    fireEvent.keyDown(box(), { key: 'End' });
    expect(active()).toContain('ORD-0007');

    fireEvent.keyDown(box(), { key: 'ArrowUp' });
    expect(active()).toContain('Annette Fox');

    fireEvent.keyDown(box(), { key: 'Home' });
    expect(active()).toContain('Ann Taylor');
  });

  it('opens the active row on Enter and closes', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const { onClose } = await ready();

    fireEvent.keyDown(box(), { key: 'Enter' });

    expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/12');
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('opens the row it moved to', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    await ready();
    fireEvent.keyDown(box(), { key: 'End' });
    fireEvent.keyDown(box(), { key: 'Enter' });

    expect(visit).toHaveBeenCalledWith('/lathe/resources/orders/7');
  });

  it('opens a row on click', async () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    await ready();
    fireEvent.click(screen.getByText('Annette Fox'));

    expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/14');
  });

  it('closes on Escape and on mod+k', async () => {
    const { onClose } = await ready();

    fireEvent.keyDown(box(), { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);

    fireEvent.keyDown(box(), { key: 'k', ctrlKey: true });
    expect(onClose).toHaveBeenCalledTimes(2);
  });
});

describe('on a phone', () => {
  it('fills the screen below 768 px and closes with Cancel', () => {
    const { onClose } = renderPalette();
    const dialog = screen.getByRole('dialog', { name: 'Search' });

    // 640 px on a wider screen; below md the dialog takes the whole viewport.
    expect(dialog.className).toContain('max-w-[640px]');
    expect(dialog.className).toContain('max-md:h-dvh');
    expect(dialog.className).toContain('max-md:max-w-none');
    expect(dialog.className).toContain('max-md:rounded-none');
    const cancel = screen.getByRole('button', { name: 'Cancel' });
    expect(cancel.className).toContain('md:hidden');

    fireEvent.click(cancel);

    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

describe('recent searches', () => {
  async function choose(term: string, body: unknown) {
    answer(body);
    vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const view = renderPalette();

    await typeTerm(term);
    fireEvent.keyDown(box(), { key: 'Enter' });
    view.unmount();
  }

  function stored(): string[] {
    return (JSON.parse(localStorage.getItem(recentsKey) ?? '[]') as { url: string }[]).map((entry) => entry.url);
  }

  function single(id: number) {
    return {
      groups: [{ resourceName: 'customers', label: 'Customers', results: [row('customers', id, `Customer ${String(id)}`)] }],
      skipped: [],
    };
  }

  it('stores a chosen result and lists it when the input is empty', async () => {
    await choose('ann', found);
    renderPalette();

    expect(stored()).toEqual(['/resources/customers/12']);
    expect(screen.getByText('Recent searches')).toBeTruthy();
    expect(within(screen.getByRole('listbox')).getByText('Ann Taylor')).toBeTruthy();
  });

  it('keeps one entry per URL, newest first, at most 5', async () => {
    for (const id of [1, 2, 3, 4, 5, 6]) {
      await choose(`c${String(id)}`, single(id));
    }

    await choose('c3', single(3));

    expect(stored()).toEqual([3, 6, 5, 4, 2].map((id) => `/resources/customers/${String(id)}`));
  });

  it('removes one entry and clears all', async () => {
    await choose('c1', single(1));
    await choose('c2', single(2));
    renderPalette();

    fireEvent.click(screen.getByRole('button', { name: 'Remove Customer 2' }));

    expect(stored()).toEqual(['/resources/customers/1']);

    fireEvent.click(screen.getByRole('button', { name: 'Clear' }));

    expect(stored()).toEqual([]);
    expect(screen.queryAllByRole('option')).toHaveLength(0);
  });

  it('opens a recent entry on Enter', async () => {
    await choose('c1', single(1));
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);

    renderPalette();
    fireEvent.keyDown(box(), { key: 'Enter' });

    expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/1');
  });

  it('still works with storage blocked, with no recents', async () => {
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    answer(found);
    renderPalette();

    expect(screen.queryByText('Recent searches')).toBeNull();

    await typeTerm('ann');
    fireEvent.keyDown(box(), { key: 'Enter' });

    expect(visit).toHaveBeenCalledWith('/lathe/resources/customers/12');
  });
});

describe('in the layout', () => {
  const enabled = { globalSearch: { enabled: true, debounce: 0.5 } };

  it('opens from the header button, pauses single-key shortcuts and returns focus on close', async () => {
    renderPage('Dashboard', dashboard, { shared: enabled });
    const opener = screen.getByRole('button', { name: 'Search' });

    opener.focus();
    fireEvent.click(opener);

    // The palette is a lazy chunk, so its first open waits for the import.
    await act(async () => {
      await vi.dynamicImportSettled();
    });

    expect(screen.getByRole('dialog')).toBeTruthy();
    expect(document.activeElement).toBe(box());
    expect(__panel.shortcutPauseCount()).toBe(1);

    fireEvent.keyDown(box(), { key: 'Escape' });

    expect(screen.queryByRole('dialog')).toBeNull();
    expect(__panel.shortcutPauseCount()).toBe(0);
    expect(document.activeElement).toBe(opener);
  });

  it('opens on mod+k from anywhere and closes on mod+k from inside', () => {
    renderPage('Dashboard', dashboard, { shared: enabled });

    fireEvent.keyDown(document.body, { key: 'k', ctrlKey: true });
    expect(screen.getByRole('dialog')).toBeTruthy();

    fireEvent.keyDown(box(), { key: 'k', ctrlKey: true });
    expect(screen.queryByRole('dialog')).toBeNull();
    expect(__panel.shortcutPauseCount()).toBe(0);
  });

  it('has neither button nor shortcut when the search is off', () => {
    renderPage('Dashboard', dashboard, { shared: { globalSearch: { enabled: false, debounce: 0.5 } } });

    fireEvent.keyDown(document.body, { key: 'k', ctrlKey: true });

    expect(screen.queryByRole('button', { name: 'Search' })).toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
  });
});
