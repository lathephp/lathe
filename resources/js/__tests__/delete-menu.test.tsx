import { router } from '@inertiajs/react';
import { __panel } from '@lathephp/core';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  __panel.toasts.set([]);
});

interface TestRow {
  id: number;
  trashed?: boolean;
  abilities?: Record<string, boolean>;
}

function renderPosts(pageRows: TestRow[], softDeletes = true) {
  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'posts',
        label: 'Posts',
        singularLabel: 'Post',
        perPageOptions: [25],
        searchable: false,
        softDeletes,
        abilities: { create: false },
        definition: { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes },
      },
      table: {
        columns: [{ key: 'title', label: 'Title', component: 'text', sortable: false, sortKey: 'title' }],
        sortKeys: [],
        rows: pageRows.map((row) => ({
          id: row.id,
          title: `Post ${String(row.id)}`,
          cells: { title: { value: `Post ${String(row.id)}`, display: `Post ${String(row.id)}` } },
          abilities: row.abilities ?? { view: true, delete: true, restore: true, forceDelete: true },
          ...(softDeletes ? { trashed: row.trashed === true } : {}),
        })),
        rowKey: 'id',
      },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: { value: pageRows.length, exact: true, capped: false, estimated: false },
    },
    { url: '/lathe/resources/posts' },
  );
}

function tick(...ids: number[]) {
  const [, body] = screen.getAllByRole('rowgroup');
  const boxes = within(body as HTMLElement).getAllByRole('checkbox');

  for (const id of ids) {
    fireEvent.click(boxes[id - 1] as HTMLElement);
  }
}

async function items(): Promise<string[]> {
  fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));

  return within(screen.getByRole('menu', { name: 'Delete' }))
    .getAllByRole('menuitem')
    .map((item) => item.textContent);
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });

describe('the delete menu', () => {
  it('shows nothing with no row ticked', () => {
    renderPosts([{ id: 1 }, { id: 2 }]);

    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
  });

  it('offers delete and force delete for live rows, restore once a trashed row is ticked', async () => {
    renderPosts([{ id: 1 }, { id: 2, trashed: true }]);
    tick(1);
    expect(await items()).toEqual(['Delete Selected', 'Force Delete Selected']);

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    tick(2);
    expect(await items()).toEqual(['Delete Selected', 'Force Delete Selected', 'Restore Selected']);
  });

  it('leaves out Delete Selected when the policy denies delete', () => {
    renderPosts([{ id: 1, abilities: { view: true, delete: false } }], false);
    tick(1);

    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
  });

  it('confirms with the count, deletes the selection and toasts the result', async () => {
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ deleted: 2, skipped: 0 }));
    renderPosts([{ id: 1 }, { id: 2 }, { id: 3 }]);
    tick(1, 3);
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete Selected' }));

    const dialog = screen.getByRole('alertdialog', { name: 'Delete 2 resource(s)?' });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete Selected' }));

    await waitFor(() => {
      expect(__panel.toasts.get().map((toast) => toast.message)).toEqual(['2 resource(s) deleted.']);
    });
    const [url, init] = fetch.mock.calls[0] ?? [];
    expect(url).toBe('/lathe/api/resources/posts/batch');
    expect(init?.method).toBe('DELETE');
    expect(JSON.parse(init?.body as string)).toMatchObject({ selection: { mode: 'ids', ids: [1, 3] } });
    expect(reload).toHaveBeenCalledWith({ only: ['table', 'count', 'tabCounts', 'summaries'] });
  });

  it('focuses the confirm, closes it on Escape and gives the focus back to Delete', async () => {
    renderPosts([{ id: 1 }, { id: 2 }]);
    tick(1);
    const trigger = await screen.findByRole('button', { name: 'Delete' });
    trigger.focus();
    fireEvent.click(trigger);
    const item = screen.getByRole('menuitem', { name: 'Delete Selected' });
    item.focus();
    fireEvent.click(item);
    const dialog = screen.getByRole('alertdialog', { name: 'Delete 1 resource(s)?' });

    expect(dialog.contains(document.activeElement)).toBe(true);

    fireEvent.keyDown(document.activeElement ?? document.body, { key: 'Escape' });

    expect(screen.queryByRole('alertdialog')).toBeNull();
    expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Delete' }));
  });

  it('toasts a failure with Retry', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(json({ message: 'Server Error' }, 500));
    renderPosts([{ id: 1, trashed: true }]);
    tick(1);
    fireEvent.click(await screen.findByRole('button', { name: 'Delete' }));
    fireEvent.click(screen.getByRole('menuitem', { name: 'Restore Selected' }));
    fireEvent.click(within(screen.getByRole('alertdialog')).getByRole('button', { name: 'Restore Selected' }));

    await waitFor(() => {
      expect(__panel.toasts.get()[0]?.message).toBe('Operation failed. Please try again.');
    });
    expect(__panel.toasts.get()[0]?.action?.label).toBe('Retry');
  });
});
