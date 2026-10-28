import { usePage } from '@inertiajs/react';
import { request, useLathe } from '@lathephp/core';
import { useEffect, useState } from 'react';
import type { Cell } from '../../fields/types';
import type { BoardCounts, BoardPage, Count, IndexProps, Row } from '../types';

type Board = NonNullable<IndexProps['table']['board']>;

/** One Board column: its option, the cards loaded so far, and its count once the counts arrive. */
export interface BoardColumn {
  /** The option's value; null is the No value column. */
  value: string | null;
  label: string;
  rows: Row[];
  page: number;
  hasMore: boolean;
  loading: boolean;
  count?: Count;
}

// Keys of the index URL that do not narrow the records: the layout, the panel and the table's paging.
const ignored = new Set(['layout', 'preview', 'page', 'per_page']);

/** The index's own query keys from its URL, the filter bar, search, view and sort, as the Board's requests send them. */
function listQuery(url: string): string {
  const params = new URLSearchParams(url.split('?')[1] ?? '');

  return new URLSearchParams([...params].filter(([key]) => !ignored.has(key))).toString();
}

/** Where a moved card was, so a refused move can put it back as it was. */
export interface Moved {
  row: Row;
  from: string | null;
  to: string | null;
  index: number;
  counts: { from?: Count; to?: Count };
}

// A capped count stays capped: one card more or less does not change "10,000+".
function bump(count: Count | undefined, by: number): Count | undefined {
  return count === undefined || count.capped ? count : { ...count, value: count.value + by };
}

function compare(a: unknown, b: unknown): number {
  return typeof a === 'number' && typeof b === 'number' ? a - b : String(a).localeCompare(String(b), undefined, { numeric: true });
}

/**
 * The index the list's sort gives a card among a column's loaded cards: its first ?sort= key,
 * or newest first by id with none. A card without that value goes first.
 */
function placeOf(rows: Row[], row: Row, sort: string): number {
  const token = sort.split(',')[0] ?? '';
  const key = token.replace(/^-/, '');
  const descending = token === '' || token.startsWith('-');
  const valueOf = (each: Row): unknown => (key === '' ? each.id : each.cells[key]?.value);
  const mine = valueOf(row);

  if (mine === undefined || mine === null) {
    return 0;
  }

  const at = rows.findIndex((each) => (descending ? compare(valueOf(each), mine) < 0 : compare(valueOf(each), mine) > 0));

  return at === -1 ? rows.length : at;
}

/** Takes the card out of its column and into another, with the field's new value; undefined when it is not on the Board or already there. */
function moveIn(columns: BoardColumn[], id: Row['id'], to: string | null, key: string, sort: string): { columns: BoardColumn[]; moved: Moved } | undefined {
  const source = columns.find((column) => column.rows.some((row) => row.id === id));
  const target = columns.find((column) => column.value === to);
  const index = source?.rows.findIndex((row) => row.id === id) ?? -1;
  const row = source?.rows[index];

  if (source === undefined || target === undefined || row === undefined || source === target) {
    return undefined;
  }

  const card = { ...row, cells: { ...row.cells, [key]: { value: to, display: target.label } } };
  const rows = target.rows.slice();
  rows.splice(placeOf(rows, card, sort), 0, card);

  return {
    columns: columns.map((column) =>
      column === source ? { ...column, rows: column.rows.filter((each) => each.id !== id), count: bump(column.count, -1) } : column === target ? { ...column, rows, count: bump(column.count, 1) } : column,
    ),
    moved: { row, from: source.value, to, index, counts: { from: source.count, to: target.count } },
  };
}

function emptyColumns(board: Board): BoardColumn[] {
  const options = Array.isArray(board.field.options) ? (board.field.options as { value: string | number; label: string }[]) : [];
  const values: { value: string | null; label: string }[] = options.map((option) => ({ value: String(option.value), label: option.label }));

  if (board.noValue) {
    values.push({ value: null, label: '' });
  }

  return values.map((option) => ({ ...option, rows: [], page: 0, hasMore: false, loading: true }));
}

/**
 * The Board's columns and their cards. Each column asks for its first page, then the counts
 * follow in one request; a filter, search, view or sort visit starts every column again at page 1.
 */
export function useBoardColumns(uriKey: string, board: Board) {
  const { apiPath } = useLathe();
  const url = usePage().url;
  const query = listQuery(url);
  const sort = new URLSearchParams(url.split('?')[1] ?? '').get('sort') ?? '';
  const [loaded, setLoaded] = useState(() => ({ query, columns: emptyColumns(board) }));
  const endpoint = `${apiPath}/resources/${uriKey}/board`;

  // A new query empties every column at once, before its first pages arrive.
  if (loaded.query !== query) {
    setLoaded({ query, columns: emptyColumns(board) });
  }

  /** Changes the columns of this query; a reply for a query the list has since left is dropped. */
  function setColumns(forQuery: string, change: (current: BoardColumn[]) => BoardColumn[]) {
    setLoaded((current) => (current.query === forQuery ? { ...current, columns: change(current.columns) } : current));
  }

  function update(forQuery: string, value: string | null, change: (column: BoardColumn) => Partial<BoardColumn>) {
    setColumns(forQuery, (current) => current.map((column) => (column.value === value ? { ...column, ...change(column) } : column)));
  }

  async function load(forQuery: string, value: string | null, page: number) {
    const params = new URLSearchParams([['column', value ?? ''], ['page', String(page)], ...new URLSearchParams(forQuery)]);

    try {
      const reply = await request<BoardPage>(`${endpoint}?${params.toString()}`);
      // A card moved in since the last page may come again on this one; it keeps its first place.
      update(forQuery, value, (column) => {
        const kept = page === 1 ? [] : column.rows;
        const fresh = (reply?.rows ?? []).filter((row) => !kept.some((each) => each.id === row.id));

        return { rows: [...kept, ...fresh], page, hasMore: reply?.hasMore === true, loading: false };
      });
    } catch {
      update(forQuery, value, () => ({ loading: false }));
    }
  }

  useEffect(() => {
    void Promise.all(emptyColumns(board).map((column) => load(query, column.value, 1)))
      .then(() => request<BoardCounts>(`${endpoint}/counts${query === '' ? '' : `?${query}`}`))
      .then((reply) => {
        setColumns(query, (current) => current.map((column) => ({ ...column, count: reply?.columns.find((each) => each.value === column.value)?.count })));
      })
      .catch(() => undefined);
    // The board's field is fixed for the page; only the list's query starts the columns again.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query]);

  return {
    columns: loaded.columns,
    showMore: (value: string | null) => {
      const column = loaded.columns.find((each) => each.value === value);

      if (column !== undefined && !column.loading) {
        update(query, value, () => ({ loading: true }));
        void load(query, value, column.page + 1);
      }
    },
    /** Moves a card to the column of `to` where the sort puts it, with both counts; says where it was. */
    move: (id: Row['id'], to: string | null): Moved | undefined => {
      const moved = moveIn(loaded.columns, id, to, board.field.key, sort);

      if (moved !== undefined) {
        setColumns(query, (current) => moveIn(current, id, to, board.field.key, sort)?.columns ?? current);
      }

      return moved?.moved;
    },
    /** Puts a moved card back at its old place with its old cells, and both counts as they were. */
    putBack: (moved: Moved) => {
      setColumns(query, (current) =>
        current.map((column) => {
          const rows = column.rows.filter((row) => row.id !== moved.row.id);

          if (column.value === moved.from) {
            rows.splice(moved.index, 0, moved.row);

            return { ...column, rows, count: moved.counts.from };
          }

          return column.value === moved.to ? { ...column, rows, count: moved.counts.to } : { ...column, rows };
        }),
      );
    },
    /** Takes a save's reply onto the card: its cells and its new stale token. */
    saved: (id: Row['id'], fields: Record<string, Cell>, updatedAt: string | null | undefined) => {
      setColumns(query, (current) =>
        current.map((column) => ({ ...column, rows: column.rows.map((row) => (row.id === id ? { ...row, cells: { ...row.cells, ...fields }, updatedAt } : row)) })),
      );
    },
  };
}
