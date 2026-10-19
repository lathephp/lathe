import { router } from '@inertiajs/react';
import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

function renderDetail(trashed: boolean, abilities: Partial<Record<'delete' | 'restore' | 'forceDelete' | 'update', boolean>>) {
  return renderPage('Resources/Detail', {
    resource: { uriKey: 'posts', label: 'Posts', singularLabel: 'Post', abilities: { create: true } },
    record: {
      id: 7,
      title: 'Hello World',
      subtitle: '',
      avatar: null,
      fields: [{ key: 'title', attribute: 'title', label: 'Title', component: 'text-field', value: 'Hello World', display: 'Hello World', stacked: false, compact: false, textAlign: 'left' }],
      layout: [{ type: 'panel', attribute: 'details', name: 'Details', fields: ['title'], toolbar: true, collapsible: false, collapsed: false, hideHeading: false, headingInCard: false, help: null, limit: null, columns: null, columnSpan: null, borderless: false }],
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false, ...abilities },
      trashed,
    },
    breadcrumbs: [],
  });
}

const ok = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });

describe('detail actions', () => {
  it('deletes after a confirm whose button uses the destructive colour, then goes to the list', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(ok({ redirect: 'posts' }));
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderDetail(false, { delete: true });

    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));
    const dialog = screen.getByRole('dialog');
    const confirm = within(dialog).getByRole('button', { name: 'Delete' });
    expect(confirm.className).toContain('bg-destructive');
    expect(confirm.className).toContain('lathe-button-size-default');
    expect(within(dialog).getByRole('button', { name: 'Cancel' }).className).toBe('lathe-button lathe-button-outline lathe-button-size-default');
    expect(screen.getAllByRole('button', { name: 'Delete', hidden: true })[0]?.className).toContain('lathe-button-outline');
    fireEvent.click(confirm);

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/posts');
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/posts/7');
    expect(fetch.mock.calls[0]?.[1]?.method).toBe('DELETE');
  });

  it('shows no Delete without the ability', () => {
    renderDetail(false, { delete: false });

    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
  });

  it('shows a trashed record with its fields, a Soft Deleted banner and Restore and Force Delete', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(ok({ redirect: 'posts' }));
    const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
    renderDetail(true, { restore: true, forceDelete: true, delete: true, update: true });

    expect(screen.getByRole('status').textContent).toBe('Soft Deleted');
    expect(screen.getAllByText('Hello World').length).toBeGreaterThan(0);
    expect(screen.queryByRole('button', { name: 'Delete' })).toBeNull();
    expect(screen.queryByRole('link', { name: /Edit/ })).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Restore' }));
    await waitFor(() => {
      expect(reload).toHaveBeenCalled();
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/posts/7/restore');
    expect(fetch.mock.calls[0]?.[1]?.method).toBe('PUT');
  });

  it('force deletes a trashed record after a confirm and goes to the list', async () => {
    const fetch = vi.spyOn(globalThis, 'fetch').mockResolvedValue(ok({ redirect: 'posts' }));
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderDetail(true, { forceDelete: true });

    expect(screen.queryByRole('button', { name: 'Restore' })).toBeNull();
    fireEvent.click(screen.getByRole('button', { name: 'Force Delete' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Force Delete' }));

    await waitFor(() => {
      expect(visit).toHaveBeenCalledWith('/lathe/resources/posts');
    });
    expect(fetch.mock.calls[0]?.[0]).toBe('/lathe/api/resources/posts/7/force');
  });
});
