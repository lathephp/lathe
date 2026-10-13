import { router } from '@inertiajs/react';
import { __panel, pauseShortcuts, resumeShortcuts } from '@lathephp/core';
import { fireEvent, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const definition = { debounce: 0.5, paginationType: 'links', clickAction: 'detail', polling: null, showPollingToggle: false, tableStyle: 'default', showColumnBorders: false, softDeletes: false };

function renderIndex(create: boolean, rows: number, uriKey = 'orders', label = 'Orders') {
  return renderPage(
    'Resources/Index',
    {
      resource: { uriKey, label, singularLabel: label.slice(0, -1), perPageOptions: [25], searchable: false, softDeletes: false, abilities: { create }, definition },
      table: {
        columns: [{ key: 'number', label: 'Number', component: 'text' }],
        rows: Array.from({ length: rows }, (_, index) => ({ id: index + 1, title: `#${String(index + 1)}`, cells: { number: { value: index + 1, display: String(index + 1) } }, abilities: { view: true } })),
        rowKey: 'id',
      },
      state: { page: 1, perPage: 25, hasMorePages: false },
      count: { value: rows, exact: true, capped: false, estimated: false },
    },
    { url: `/lathe/resources/${uriKey}` },
  );
}

function renderDetail(update: boolean) {
  return renderPage('Resources/Detail', {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } },
    record: { id: 12, title: 'ORD-00012', subtitle: '', avatar: null, fields: [], layout: [], abilities: { view: true, update, delete: false, replicate: false, restore: false, forceDelete: false }, trashed: false },
    breadcrumbs: [],
  });
}

describe('c and e', () => {
  it('opens the create form on c only when create is allowed', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const { unmount } = renderIndex(true, 3);

    const create = screen.getByRole('link', { name: /Create Order/ });
    expect(create.getAttribute('href')).toBe('/lathe/resources/orders/new');
    expect(create.getAttribute('aria-keyshortcuts')).toBe('c');
    expect(create.querySelector('kbd')?.textContent).toBe('C');

    fireEvent.keyDown(document.body, { key: 'c' });
    expect(visit).toHaveBeenCalledWith('/lathe/resources/orders/new');

    unmount();
    visit.mockClear();
    renderIndex(false, 3);
    expect(screen.queryByRole('link', { name: /Create Order/ })).toBeNull();
    fireEvent.keyDown(document.body, { key: 'c' });
    expect(visit).not.toHaveBeenCalled();
  });

  it('does nothing on c while a modal is open or focus is in an input', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    renderIndex(true, 3);

    pauseShortcuts();
    fireEvent.keyDown(document.body, { key: 'c' });
    resumeShortcuts();

    const input = document.createElement('input');
    document.body.append(input);
    fireEvent.keyDown(input, { key: 'c' });
    input.remove();

    expect(visit).not.toHaveBeenCalled();
  });

  it('opens the edit form on e only when update is allowed', () => {
    const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
    const { unmount } = renderDetail(true);

    expect(screen.getByRole('link', { name: /Edit/ }).getAttribute('aria-keyshortcuts')).toBe('e');
    fireEvent.keyDown(document.body, { key: 'e' });
    expect(visit).toHaveBeenCalledWith('/lathe/resources/orders/12/edit');

    unmount();
    visit.mockClear();
    renderDetail(false);
    fireEvent.keyDown(document.body, { key: 'e' });
    expect(visit).not.toHaveBeenCalled();
  });

  it('hides every hint when keyboard_shortcuts.enabled is false', () => {
    renderIndex(true, 3);
    __panel.sharedProps.set({ ...__panel.sharedProps.get(), keyboardShortcuts: false });
    const { container } = renderDetail(true);

    expect(container.querySelectorAll('kbd')).toHaveLength(0);
  });
});

describe('empty state', () => {
  it('shows the Create button on a Resource with no rows when create is allowed, and none otherwise', () => {
    const { unmount } = renderIndex(true, 0, 'customers', 'Customers');

    expect(screen.getByText('No customers yet')).toBeDefined();
    expect(screen.getByRole('link', { name: 'Create' }).getAttribute('href')).toBe('/lathe/resources/customers/new');

    unmount();
    renderIndex(false, 0, 'customers', 'Customers');
    expect(screen.queryByRole('link', { name: 'Create' })).toBeNull();
  });
});
