import { act, fireEvent, renderHook, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useRowSelection } from '../index/useRowSelection';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
});

const columns = [{ key: 'number', label: 'Number', component: 'text', sortable: false, sortKey: 'number' }];

function rows(count: number) {
  return Array.from({ length: count }, (_, index) => {
    const number = `ORD-${String(index + 1).padStart(5, '0')}`;

    return { id: index + 1, title: number, cells: { number: { value: number, display: number } }, abilities: { view: true } };
  });
}

function renderOrders(count: { value: number; exact: boolean; capped: boolean; estimated: boolean }) {
  vi.spyOn(navigator, 'platform', 'get').mockReturnValue('Linux x86_64');

  return renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
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
      table: { columns, sortKeys: [], rows: rows(25), rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: true, sort: '' },
      count,
    },
    { url: '/lathe/resources/orders?tab=pending' },
  );
}

function rowBoxes(): HTMLInputElement[] {
  const [, body] = screen.getAllByRole('rowgroup');

  return within(body as HTMLElement).getAllByRole<HTMLInputElement>('checkbox');
}

describe('row selection', () => {
  it('offers every matching row once mod+a ticks the page', () => {
    renderOrders({ value: 120, exact: true, capped: false, estimated: false });

    fireEvent.keyDown(document.body, { key: 'a', ctrlKey: true });

    expect(rowBoxes().every((box) => box.checked)).toBe(true);
    expect(screen.getByText('25 selected')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Select all 120' }));

    expect(screen.getByText('All 120 selected')).toBeTruthy();
  });

  it('selects all matching on mod+shift+a, reading the capped count', () => {
    renderOrders({ value: 10000, exact: false, capped: true, estimated: false });

    fireEvent.keyDown(document.body, { key: 'A', ctrlKey: true, shiftKey: true });

    expect(screen.getByText('All 10,000+ selected')).toBeTruthy();
    expect(rowBoxes().every((box) => box.checked)).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Clear' }));

    expect(rowBoxes().some((box) => box.checked)).toBe(false);
  });

  it('swaps the toolbar row for the selection bar in place, so the header row stays put', () => {
    renderOrders({ value: 10000, exact: true, capped: false, estimated: false });
    const toolbar = document.querySelector<HTMLElement>('[data-lathe-toolbar]');
    const card = document.querySelector('[data-lathe-card]');
    const before = [...(card?.children ?? [])];

    for (const box of rowBoxes().slice(0, 3)) {
      fireEvent.click(box);
    }

    const bar = document.querySelector<HTMLElement>('[data-lathe-toolbar]');

    // The same row, so the same height, and nothing new above the table.
    expect(bar).toBe(toolbar);
    expect([...(card?.children ?? [])]).toEqual(before);
    expect(within(bar as HTMLElement).getByText('3 selected')).toBeTruthy();
    expect(within(bar as HTMLElement).getByRole('button', { name: 'Select all 10,000' })).toBeTruthy();
    expect(within(bar as HTMLElement).queryByRole('searchbox')).toBeNull();

    fireEvent.click(within(bar as HTMLElement).getByRole('button', { name: 'Clear' }));

    expect(rowBoxes().some((box) => box.checked)).toBe(false);
    expect(within(bar as HTMLElement).queryByText('3 selected')).toBeNull();
  });
});

describe('the selection descriptor', () => {
  it('sends the ticked ids', () => {
    const { result } = renderHook(() => useRowSelection());

    act(() => {
      result.current.toggle(3);
    });
    act(() => {
      result.current.toggle(5);
    });

    expect(result.current.descriptor({ q: 'ada' })).toEqual({
      mode: 'ids',
      ids: [3, 5],
      exclusions: [],
      q: 'ada',
      filters: {},
      tab: null,
      saved_tab: null,
      trashed: null,
      lens: null,
      via: null,
    });
  });

  it('sends mode all with the index state and each unticked row as an exclusion', () => {
    const { result } = renderHook(() => useRowSelection());

    act(() => {
      result.current.selectAllMatching();
    });
    act(() => {
      result.current.toggle(7);
    });

    expect(result.current.isSelected(7)).toBe(false);
    expect(result.current.isSelected(8)).toBe(true);
    expect(result.current.descriptor({ tab: 'pending', filter: { status: 'pending' }, trashed: 'with' }, 'big-spenders')).toEqual({
      mode: 'all',
      ids: [],
      exclusions: [7],
      q: '',
      filters: { status: 'pending' },
      tab: 'pending',
      saved_tab: null,
      trashed: 'with',
      lens: 'big-spenders',
      via: null,
    });
  });
});
