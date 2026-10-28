import { usePage } from '@inertiajs/react';
import { request, useLathe } from '@lathephp/core';
import { useEffect, useState } from 'react';
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
  const query = listQuery(usePage().url);
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
      update(forQuery, value, (column) => ({ rows: [...(page === 1 ? [] : column.rows), ...(reply?.rows ?? [])], page, hasMore: reply?.hasMore === true, loading: false }));
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
  };
}
