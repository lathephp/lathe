import { App, router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { definition, firstCell, orders, panel, press, previews, reply, urlOf } from './preview-support';
import { ResourceTable } from '../index/ResourceTable';
import { cellOf, editCell, fakeInertiaServer, pick, renderPage, sharedProps } from './support';

beforeEach(() => {
  fakeInertiaServer();
  router.flushAll();
  window.history.replaceState({}, '', '/lathe/resources/orders');
  // A save to orders empties the panel's cache, which outlives a test.
  window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders' }));
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.useRealTimers();
});

describe('the preview panel', () => {
  it('opens on the cursor row with Space, heads it with the row card at once and puts the id in the URL without a history entry', async () => {
    let answer: ((response: Response) => void) | null = null;
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
    // The badge is the row's own Status cell.
    expect(panel().querySelector('header')?.textContent).toContain('Pending');
    await waitFor(() => {
      expect(new URLSearchParams(window.location.search).get('preview')).toBe('1');
    });
    expect(window.history.length).toBe(entries);

    await waitFor(() => {
      expect(answer).not.toBeNull();
    });
    act(() => {
      answer?.(new Response(JSON.stringify(reply(1)), { status: 200 }));
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

  it('leaves Space on a row checkbox, or on its cell, to tick the row', async () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    const box = screen.getAllByRole('checkbox')[1] as HTMLInputElement;

    box.focus();
    await press(' ');
    expect(panel()).toBeNull();

    // The cursor on the checkbox column: Space ticks, as Enter does.
    (box.closest('td') as HTMLElement).focus();
    await press(' ');
    expect(box.checked).toBe(true);
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

describe('the panel following the cursor', () => {
  async function openOnFirst(): Promise<string[]> {
    const asked = previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(asked).toHaveLength(1);
    });
    await within(panel()).findByText('Oct 18, 2026');

    return asked;
  }

  it('changes the header at once on Down and asks for the body 150 ms after the cursor stops', async () => {
    const asked = await openOnFirst();
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

    fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowDown' });
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79186507');

    act(() => {
      vi.advanceTimersByTime(149);
    });
    expect(asked).toHaveLength(1);
    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(asked).toHaveLength(2);
    expect(asked[1]).toBe('/lathe/api/resources/orders/2/preview');
  });

  it('sends one request, for the last row, when Down is pressed 5 times 50 ms apart', async () => {
    const asked = await openOnFirst();
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });

    for (let step = 0; step < 5; step++) {
      fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowDown' });
      act(() => {
        vi.advanceTimersByTime(50);
      });
    }
    act(() => {
      vi.advanceTimersByTime(500);
    });

    expect(asked.slice(1)).toEqual(['/lathe/api/resources/orders/6/preview']);
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79154831');
  });

  it('asks nothing for an order it has loaded, until a save to any order', async () => {
    const asked = await openOnFirst();

    fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowDown' });
    await waitFor(() => {
      expect(asked).toHaveLength(2);
    });
    fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowUp' });
    expect(within(panel()).getByText('Oct 18, 2026')).toBeTruthy();
    await act(async () => {
      await new Promise((resolve) => setTimeout(resolve, 300));
    });
    expect(asked).toHaveLength(2);

    // A save to another order empties the cache; the open record comes again.
    act(() => {
      window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders/5/field/status' }));
    });
    await waitFor(() => {
      expect(asked).toHaveLength(3);
    });
    expect(asked[2]).toBe('/lathe/api/resources/orders/1/preview');
  });

  it('shows Paid in the header and the body after an inline edit of the cursor row', async () => {
    const asked: string[] = [];
    let paid = false;
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);

      if (url.endsWith('/field/status')) {
        paid = true;

        return Promise.resolve(new Response(JSON.stringify({ fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 't1' }), { status: 200 }));
      }

      if (url.endsWith('/preview')) {
        asked.push(url);

        return Promise.resolve(new Response(JSON.stringify(reply(1, paid ? 'Paid' : 'Pending')), { status: 200 }));
      }

      return Promise.resolve(new Response(null, { status: 204 }));
    });
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await within(await waitFor(() => panel())).findByText('Pending', { selector: 'dd *, dd' });

    await editCell('Status');
    await pick('Paid');

    await waitFor(() => {
      expect(within(panel()).getByText('Paid', { selector: 'dd *, dd' })).toBeTruthy();
    });
    expect(within(panel()).queryByText('Pending')).toBeNull();
    expect(panel().querySelector('header')?.textContent).toContain('Paid');
    expect(cellOf('Status').textContent).toContain('Paid');
  });

  it('keeps each edited row new in the header after edits on two rows', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) => {
      const url = urlOf(input);
      const match = /\/orders\/(\d+)\/preview$/.exec(url);

      if (url.endsWith('/field/status')) {
        return Promise.resolve(new Response(JSON.stringify({ fields: { status: { value: 'paid', display: 'Paid' } }, updatedAt: 't1' }), { status: 200 }));
      }

      return Promise.resolve(match === null ? new Response(null, { status: 204 }) : new Response(JSON.stringify(reply(Number(match[1]), 'Paid')), { status: 200 }));
    });
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await waitFor(() => panel());

    for (const row of [0, 1]) {
      await editCell('Status', 'Enter', row);
      await pick('Paid');
      await waitFor(() => {
        expect(screen.queryByText('Saving…')).toBeNull();
      });
    }
    fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowUp' });

    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79194426');
    expect(panel().querySelector('header')?.textContent).toContain('Paid');
    expect(panel().querySelector('header')?.textContent).not.toContain('Pending');
  });

  it('says an order deleted since the list loaded was deleted, with Close', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(urlOf(input).endsWith('/preview') ? new Response(JSON.stringify({ message: 'Not found.' }), { status: 404 }) : new Response(null, { status: 204 })),
    );
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');

    expect(await within(await waitFor(() => panel())).findByText('This order was deleted.')).toBeTruthy();
    expect(within(panel()).getByRole('button', { name: 'Close' })).toBeTruthy();
    expect(within(panel()).queryByRole('link', { name: 'Edit' })).toBeNull();
  });
});

describe('the openers of the old preview modal', () => {
  it('has no Preview button on a row and no modal', () => {
    previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });

    expect(screen.queryByRole('button', { name: /^Preview / })).toBeNull();
    expect(screen.queryByRole('dialog')).toBeNull();
  });

  it('opens the panel from a title-cell click when the click action is preview, with no visit', async () => {
    const asked = previews();
    const visit = vi.spyOn(router, 'visit');
    const props = orders();
    renderPage('Resources/Index', { ...props, resource: { ...props.resource, definition: { ...definition, clickAction: 'preview' } } }, { url: '/lathe/resources/orders' });

    fireEvent.click(cellOf('Number', 1));

    await waitFor(() => {
      expect(panel()).not.toBeNull();
    });
    expect(within(panel()).getByRole('heading').textContent).toBe('ORD-79186507');
    await waitFor(() => {
      expect(asked).toEqual(['/lathe/api/resources/orders/2/preview']);
    });
    expect(visit).not.toHaveBeenCalled();
  });
});


describe('the panel without the view ability', () => {
  it('shows no fields when the preview request answers 403', async () => {
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(urlOf(input).endsWith('/preview') ? new Response(JSON.stringify({ message: 'This action is unauthorized.' }), { status: 403 }) : new Response(null, { status: 204 })),
    );
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');

    expect(await within(await waitFor(() => panel())).findByText('You may not view this order.')).toBeTruthy();
    expect(panel().querySelector('dl')).toBeNull();
    expect(within(panel()).queryByRole('link', { name: 'Edit' })).toBeNull();
  });
});

describe('the panel cache', () => {
  it('asks again for a record another user previewed in this tab', async () => {
    const asked = previews();
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(asked).toHaveLength(1);
    });
    await press(' ');

    // Signing in as someone else is an Inertia visit: the module, and its cache, stay.
    act(() => {
      __panel.sharedProps.set({ ...__panel.sharedProps.get(), auth: { user: { name: 'Editor User', email: 'editor@lathephp.com' } } });
    });
    await press(' ');

    await waitFor(() => {
      expect(asked).toHaveLength(2);
    });
  });

  it('does not keep a reply that left before a save to its Resource', async () => {
    const answers: ((response: Response) => void)[] = [];
    vi.spyOn(globalThis, 'fetch').mockImplementation(
      (input) =>
        new Promise((resolve) => {
          if (urlOf(input).endsWith('/preview')) {
            answers.push(resolve);
          } else {
            resolve(new Response(null, { status: 204 }));
          }
        }),
    );
    renderPage('Resources/Index', orders(), { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');
    await waitFor(() => {
      expect(answers).toHaveLength(1);
    });

    // The order is saved while its preview is on the way; the old answer lands after the save.
    act(() => {
      window.dispatchEvent(new CustomEvent('lathe-write', { detail: '/lathe/api/resources/orders/1/field/status' }));
    });
    act(() => {
      answers[0]?.(new Response(JSON.stringify(reply(1, 'Pending')), { status: 200 }));
    });
    await waitFor(() => {
      expect(answers).toHaveLength(2);
    });
    act(() => {
      answers[1]?.(new Response(JSON.stringify(reply(1, 'Paid')), { status: 200 }));
    });

    expect(await within(panel()).findByText('Paid', { selector: 'dd *, dd' })).toBeTruthy();
    expect(within(panel()).queryByText('Pending', { selector: 'dd *, dd' })).toBeNull();
  });
});

describe('a plugin table without the panel', () => {
  it('opens the record page for a preview click action', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const props = orders();
    const resource = { ...props.resource, definition: { ...definition, clickAction: 'preview' } };
    const selection = { isSelected: () => false, toggle: () => undefined, toggleAll: () => undefined };
    const Plugin = () => <ResourceTable resource={resource as never} table={props.table} state={props.state as never} loading={false} selection={selection} sort={undefined} onSort={() => undefined} />;
    render(<App initialPage={{ component: 'Plugin', props: { errors: {}, ...sharedProps() }, url: '/lathe/plugins/x', version: null, rescuedProps: [], flash: {}, rememberedState: {}, sharedProps: [] }} initialComponent={Plugin} resolveComponent={() => Plugin} />);

    fireEvent.click(cellOf('Number', 1));

    expect(visit.mock.calls[0]?.[0]).toBe('/lathe/resources/orders/2');
  });
});

describe('the panel header with a hidden column', () => {
  it('draws the badge of a column the user hid from the record field once the reply lands', async () => {
    const status = { key: 'status', label: 'Status', component: 'select-field', value: 'pending', display: 'Pending', options: [{ value: 'pending', label: 'Pending' }], badges: { pending: 'warning' } };
    vi.spyOn(globalThis, 'fetch').mockImplementation((input) =>
      Promise.resolve(urlOf(input).endsWith('/preview') ? new Response(JSON.stringify({ ...reply(1), fields: [...reply(1).fields, status] }), { status: 200 }) : new Response(null, { status: 204 })),
    );
    const props = orders();
    // The user hid Status; its cell still comes, with the raw value as its text.
    const table = {
      ...props.table,
      columns: props.table.columns.filter((column) => column.key !== 'status'),
      rows: props.table.rows.map((row) => ({ ...row, cells: { ...row.cells, status: { value: 'pending', display: 'pending' } } })),
    };
    renderPage('Resources/Index', { ...props, table }, { url: '/lathe/resources/orders' });
    firstCell().focus();
    await press(' ');

    expect(await within(await waitFor(() => panel())).findByText('Oct 18, 2026')).toBeTruthy();
    const header = panel().querySelector('header') as HTMLElement;
    expect(header.textContent).toContain('Pending');
    expect(header.textContent).not.toContain('pending');
  });
});
