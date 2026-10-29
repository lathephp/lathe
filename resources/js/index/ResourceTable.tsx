import { useTranslation } from '@lathephp/core';
import { Link, router, usePage } from '@inertiajs/react';
import { Columns3, Eye, Pencil } from 'lucide-react';
import { Fragment, Suspense, useLayoutEffect, useRef, useState, type MouseEvent } from 'react';
import type { FieldSchema } from '../fields/types';
import { cn } from '../lib/cn';
import { instantProps } from '../lib/instant';
import { TableCell } from '../table/cells';
import { useGrid, type GridPreview, type GridRowSelection } from '../table/useGrid';
import type { SharedProps } from '../types';
import { GroupHeaderRow } from './GroupHeaderRow';
import { GroupSummaryRow } from './GroupSummaryRow';
import { InlineCell } from './InlineCell';
import { ColumnPicker, TableSummaryRow } from './lazy';
import { hasRowMenu, RowMenu } from './RowMenu';
import { TableSummaryPlaceholder } from './TableSummaryPlaceholder';
import type { ColumnPin, IndexProps, Row } from './types';

// A row's height at each table density; a loading row takes the same.
export const rowHeights = { compact: 'h-8', comfortable: 'h-10', spacious: 'h-14' };

// Under 768 px the table scrolls inside its card; the checkbox and the first column stay as the row's anchor.
// A stuck cell sits a layer up, so the cells scrolling beneath it, an inline select included, never paint over it.
// It takes its row's colour: the muted header, a hovered or a ticked row.
const checkboxColumn = 'sticky start-0 z-[1] w-14 bg-inherit ps-6 pe-4';
const firstColumn = 'sticky start-14 z-[1] bg-inherit';
// A pinned column stays in view too, at the offset the header cells before it add up to.
const pinnedColumn = 'sticky z-[1] bg-inherit';
// The row menu stays at the end. A sticky cell holds its popover in its own stacking context, so the
// picker's cell sits above the rows, and a row menu's cell rises while its menu is in use.
const menuColumn = 'sticky end-0 bg-inherit';
// Each edge of the pinned blocks casts a shadow while columns scroll beneath it.
const startEdge = 'shadow-[6px_0_8px_-6px_rgb(0_0_0/0.2)] rtl:shadow-[-6px_0_8px_-6px_rgb(0_0_0/0.2)]';
const endEdge = 'shadow-[-6px_0_8px_-6px_rgb(0_0_0/0.2)] rtl:shadow-[6px_0_8px_-6px_rgb(0_0_0/0.2)]';

// View and Edit sit in the DOM at rest so the cell keeps its width; the row's hover or focus shows them.
const rowLink = 'lathe-row-button opacity-0 group-hover:opacity-100 group-focus-within:opacity-100';

// The cell cursor: the focused cell, outlined inside its edges.
const cursorCell = 'focus:outline-2 focus:-outline-offset-2 focus:outline-ring';

interface ResourceTableProps extends Pick<IndexProps, 'resource' | 'table' | 'state'> {
  loading: boolean;
  /** Only the calls the table makes, so a plugin can hand in its own selection. */
  selection: GridRowSelection;
  /** The ?sort= of the URL, `-total_cents,placed_at`. */
  sort: string | undefined;
  onSort: (sort: string) => void;
  /** Whether the page draws the rows as cards below 768 px, so the table hides there. */
  cards?: boolean;
  /** The preview panel: Space opens it on the cursor's row, and it follows the cursor. */
  preview?: GridPreview;
}

/**
 * A click cycles one column through ascending, descending and off. A plain click makes it the
 * only sort; with append the other columns stay and a new one goes last.
 */
/**
 * A column's fixed width in pixels, from the field's `indexWidth()`; none lets the table size it.
 * The table lays out automatically, so the header and each cell hold it as their least and most.
 */
export function columnWidth(column: object): { width: string; minWidth: string; maxWidth: string } | undefined {
  const width = 'indexWidth' in column ? column.indexWidth : undefined;
  const px = `${String(width)}px`;

  return typeof width === 'number' ? { width: px, minWidth: px, maxWidth: px } : undefined;
}

/**
 * Whether a row is the one an inline save went over. A client visit, such as the preview panel
 * putting its record in the URL, can hand the page a copy of its rows; a reload's changed row differs.
 */
export function sameRow(saved: Row, row: Row): boolean {
  return saved === row || JSON.stringify(saved) === JSON.stringify(row);
}

/** The text alignment of a column; the index sends each column as the field's full payload, `textAlign` included. */
export function alignClass(column: object): string {
  const align = 'textAlign' in column ? column.textAlign : undefined;

  return align === 'right' ? 'text-end' : align === 'center' ? 'text-center' : 'text-start';
}

export function nextSort(sort: string | undefined, key: string, append: boolean): string {
  const tokens = (sort ?? '').split(',').filter((token) => token !== '');
  const index = tokens.findIndex((token) => token.replace(/^-/, '') === key);
  const current = tokens[index];
  const next = current === undefined ? key : current === key ? `-${key}` : null;

  if (!append) {
    return next ?? '';
  }

  if (index === -1) {
    return [...tokens, key].join(',');
  }

  return tokens.flatMap((token, position) => (position !== index ? [token] : next === null ? [] : [next])).join(',');
}

export function direction(sort: string | undefined, key: string): 'ascending' | 'descending' | 'none' {
  const tokens = (sort ?? '').split(',');

  return tokens.includes(key) ? 'ascending' : tokens.includes(`-${key}`) ? 'descending' : 'none';
}

export function ResourceTable({ resource, table, state, loading, selection, sort, onSort, cards, preview }: ResourceTableProps) {
  const { __ } = useTranslation();
  const { props: { basePath, tableStyle }, onceProps, url: pageUrl } = usePage<SharedProps>();
  // Every row of the trashed-only view is deleted, so its rows carry no Soft Deleted badge.
  const trashedOnly = new URLSearchParams(pageUrl.split('?')[1]).get('trashed') === 'only';
  const { clickAction, showColumnBorders } = resource.definition;
  // Deferred with the count, so the footer arrives after the rows.
  const { summaries, groups, lens } = usePage<Pick<IndexProps, 'summaries' | 'groups' | 'lens'>>().props;
  // The keys of the groups folded shut.
  const [collapsed, setCollapsed] = useState<string[]>([]);
  const rowClass = cn(
    'border-b border-border last:border-0',
    // TableStyle tight forces the compact height whatever the panel density.
    rowHeights[resource.definition.tableStyle === 'tight' ? 'compact' : tableStyle.density],
    tableStyle.striped && 'even:bg-muted/50',
  );
  const border = showColumnBorders && 'border-e border-border last:border-e-0';
  // A lens has no picker, nor the error page, which sends no allColumns.
  const picker = (table.allColumns ?? []).length > 0;
  const withMenu = picker || table.rows.some((row) => hasRowMenu(row) || row.abilities.view === true || row.abilities.update === true);
  // The title column first, then the pinned ones, then the rest, as the server ordered each.
  const [title, ...rest] = table.columns;
  const pinned = rest.filter((column) => column.pinned === true);
  const columns = title === undefined ? [] : [title, ...pinned, ...rest.filter((column) => column.pinned !== true)];
  const stuck = columns.length === 0 ? 0 : pinned.length + 1;
  const header = useRef<HTMLTableRowElement>(null);
  const scroller = useRef<HTMLDivElement>(null);
  const grid = useRef<HTMLTableElement>(null);
  // The data rows in the order the grid counts them: a folded group's rows are not drawn.
  const shown = table.rows.filter((row) => row.group === undefined || !collapsed.includes(row.group.key));
  const tick = useGrid(grid, shown, selection, preview);
  // The cursor's cell when the page was left, kept in its history entry, so Back puts the cursor there again.
  const [cursor] = useState(() => router.restore<{ id: string; cell: number }>('cursor'));
  const restored = cursor !== undefined && shown.some((row) => String(row.id) === cursor.id);

  // A row's cell holds the tab stop when it is the restored cursor, or else the first row's title cell.
  function stop(row: Row, cell: number) {
    return (restored ? String(row.id) === cursor.id && cell === cursor.cell : shown[0] === row && cell === 1) ? 0 : -1;
  }

  useLayoutEffect(() => {
    if (cursor !== undefined) {
      grid.current?.querySelector<HTMLElement>('tbody td[tabindex="0"]')?.focus({ preventScroll: true });
      // Read once: a later remount, such as rows coming back after an empty filter, keeps the focus where it is.
      router.remember(undefined, 'cursor');
    }

    // Only a visit that leaves the list: a poll, a filter or a hover's prefetch keeps its page.
    return router.on('before', ({ detail: { visit } }) => {
      const cell = grid.current?.querySelector<HTMLTableCellElement>('tbody td[tabindex="0"]');

      if (cell != null && !visit.prefetch && visit.url.pathname !== window.location.pathname) {
        router.remember({ id: cell.parentElement?.dataset.row, cell: cell.cellIndex }, 'cursor');
      }
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps -- once, on mount
  }, []);
  // Where each stuck column starts, measured from the header cells.
  const [offsets, setOffsets] = useState<number[]>([]);
  // Whether columns sit scrolled beneath the start block, and beyond the row menu at the end.
  const [edges, setEdges] = useState({ start: false, end: false });

  function measureEdges() {
    const box = scroller.current;

    if (box !== null) {
      const scrolled = Math.abs(box.scrollLeft);
      const start = scrolled > 0;
      const end = box.scrollWidth - box.clientWidth - scrolled > 1;
      setEdges((current) => (current.start === start && current.end === end ? current : { start, end }));
    }
  }

  useLayoutEffect(() => {
    const row = header.current;
    const box = scroller.current;

    if (row === null || box === null) {
      return;
    }

    const cells = Array.from(row.cells).slice(0, stuck + 1);
    const measure = () => {
      let start = 0;
      const next = cells.map((cell) => {
        const at = start;
        start += cell.offsetWidth;

        return at;
      });
      next.shift();
      setOffsets((current) => (current.join() === next.join() ? current : next));
      measureEdges();
    };

    measure();

    // jsdom has no ResizeObserver.
    if (typeof ResizeObserver === 'undefined') {
      return;
    }

    const observer = new ResizeObserver(measure);
    observer.observe(box);
    cells.forEach((cell) => {
      observer.observe(cell);
    });

    return () => {
      observer.disconnect();
    };
  }, [stuck, table.columns]);

  // The sticky class and offset of each column, by its place in `columns`.
  const pins: ColumnPin[] = columns.map((_, index) => {
    const edge = index === stuck - 1 && edges.start && startEdge;

    if (index === 0) {
      return { className: cn(firstColumn, edge) };
    }

    return index < stuck ? { className: cn(pinnedColumn, edge), style: { insetInlineStart: offsets[index] } } : { className: '' };
  });
  const menuClass = cn(menuColumn, edges.end && endEdge);
  // Cells and tokens an inline save sent back, by row, over the row the page had loaded; a reload's newer row wins.
  const [saved, setSaved] = useState<Record<string, { over: Row; cells: Row['cells']; updatedAt: string | null | undefined }>>({});
  // Each column's cell classes, worked out once per render rather than once per cell.
  const cellClasses = columns.map((column, index) => {
    const base = cn('max-w-80 px-3 whitespace-nowrap', alignClass(column), pins[index]?.className, border, cursorCell);

    return { plain: cn(base, 'truncate'), inline: base, style: { ...columnWidth(column), ...pins[index]?.style } };
  });
  const ids = table.rows.map((row) => row.id);
  const ticked = ids.filter((id) => selection.isSelected(id)).length;

  // Edit falls back to detail for a user who may not update the row.
  function rowUrl(row: Row): string | null {
    const detail = `${basePath}/resources/${resource.uriKey}/${String(row.id)}`;

    // A table a plugin draws without the preview panel opens the record's page instead.
    if (clickAction === 'detail' || (clickAction === 'preview' && preview === undefined)) {
      return detail;
    }

    return clickAction === 'edit' ? (row.abilities.update === true ? `${detail}/edit` : detail) : null;
  }

  // A record page opens at once with the row's title; an edit form has no skeleton, so it waits.
  function visitOptions(row: Row) {
    return clickAction === 'detail' ? { component: 'Resources/Detail', pageProps: instantProps({ title: row.title }, onceProps) } : {};
  }

  // Resting on a row's first cell for 75 ms fetches its page, kept 30 s or until a write to the resource.
  const hover = useRef<number | undefined>(undefined);

  function prefetchRow(row: Row) {
    const url = rowUrl(row);

    if (url !== null) {
      hover.current = window.setTimeout(() => {
        router.prefetch(url, visitOptions(row), { cacheFor: '30s', cacheTags: `resource:${resource.uriKey}` });
      }, 75);
    }
  }

  // The title cell runs the click action; Enter on a cell that cannot be edited presses it.
  function openRow(event: MouseEvent, row: Row) {
    const url = rowUrl(row);
    event.preventDefault();

    if (clickAction === 'select') {
      selection.toggle(row.id);
    } else if (clickAction === 'preview' && preview !== undefined) {
      preview.show(row.id);
    } else if (url !== null && (event.metaKey || event.ctrlKey)) {
      window.open(url, '_blank', 'noopener');
    } else if (url !== null) {
      router.visit(url, visitOptions(row));
    }
  }

  const placeholder = (
    <TableSummaryPlaceholder
      columns={columns}
      // A paged table totals the page and all records, an unpaged one only the records.
      rows={state.hasMorePages || state.page > 1 ? 2 : 1}
      leading={cn(checkboxColumn, border)}
      trailing={withMenu}
    />
  );

  return (
    // Relative, so the visually hidden header labels stay inside the scroll box on a narrow screen.
    <div
      ref={scroller}
      // Below 768 px the page shows the rows as cards instead.
      className={cn('relative overflow-x-auto', cards === true && 'max-md:hidden')}
      role="region"
      aria-label={__(':resource table', { resource: resource.label })}
      onScroll={measureEdges}
    >
      {/* A grid of one tab stop: the cursor's cell; the controls in the rows wait for it. */}
      <table ref={grid} role="grid" aria-rowcount={-1} data-lathe-table className="w-full text-sm">
        <thead>
          <tr ref={header}>
            <th scope="col" className={cn(checkboxColumn, border)}>
              <input
                type="checkbox"
                aria-label={__('Select all')}
                className="block size-4"
                checked={ids.length > 0 && ticked === ids.length}
                ref={(input) => {
                  if (input !== null) {
                    input.indeterminate = ticked > 0 && ticked < ids.length;
                  }
                }}
                onChange={() => {
                  selection.toggleAll(ids);
                }}
              />
            </th>
            {columns.map((column, index) => {
              const sortable = column.sortable && table.sortKeys.includes(column.sortKey);
              const sorted = sortable ? direction(sort, column.sortKey) : 'none';

              return (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={sortable ? sorted : undefined}
                  style={{ ...columnWidth(column), ...pins[index]?.style }}
                  className={cn('px-3 whitespace-nowrap', alignClass(column), pins[index]?.className, border)}
                >
                  {sortable ? (
                    <button
                      type="button"
                      className="lathe-sort"
                      onClick={(event) => {
                        onSort(nextSort(sort, column.sortKey, event.shiftKey));
                      }}
                    >
                      {column.label}
                      {/* Both arrows always; the one of the current direction is drawn in the text colour. */}
                      <svg aria-hidden="true" width="8" height="14" viewBox="0 0 8 14">
                        <path d="M4 1 7.5 5.5h-7z" />
                        <path d="M4 13 .5 8.5h7z" />
                      </svg>
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              );
            })}
            {withMenu && (
              <th scope="col" className={cn('z-20 w-12 px-3', menuClass, border)}>
                <span className="sr-only">{__('Actions')}</span>
                {picker && <ColumnsButton uriKey={resource.uriKey} table={table} />}
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {loading
            ? Array.from({ length: state.perPage }, (_, index) => (
                <tr key={index} data-skeleton="" aria-hidden="true" className={rowClass}>
                  <td className={cn(checkboxColumn, border)} />
                  {columns.map((column) => (
                    <td key={column.key} className={cn('px-3', border)}>
                      <span className="block h-3 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                    </td>
                  ))}
                  {withMenu && <td className={cn(border)} />}
                </tr>
              ))
            : table.rows.map((row, position) => {
                const group = row.group;
                const kept = saved[String(row.id)];
                const fresh = kept !== undefined && sameRow(kept.over, row) ? kept : undefined;
                const next = table.rows[position + 1];
                const folded = group !== undefined && collapsed.includes(group.key);
                // The last group may go on past the page bottom, so its subtotal would not match the rows shown.
                const subtotal =
                  group !== undefined && !folded && next?.group?.key !== group.key && (next !== undefined || !state.hasMorePages)
                    ? summaries?.groups?.[group.key]
                    : undefined;

                return (
                  <Fragment key={row.id}>
                    {group !== undefined && table.rows[position - 1]?.group?.key !== group.key && (
                      <GroupHeaderRow
                        group={group}
                        colSpan={columns.length + 1 + (withMenu ? 1 : 0)}
                        collapsible={groups?.active?.collapsible ?? false}
                        expanded={!folded}
                        onToggle={() => {
                          setCollapsed(folded ? collapsed.filter((key) => key !== group.key) : [...collapsed, group.key]);
                        }}
                      />
                    )}
                    {!folded && (
                      <tr data-row={row.id} className={cn('group', rowClass)}>
                        <td tabIndex={stop(row, 0)} className={cn(checkboxColumn, border, cursorCell)}>
                          <input
                            type="checkbox"
                            tabIndex={-1}
                            aria-label={__('Select :title', { title: row.title })}
                            className="block size-4"
                            checked={selection.isSelected(row.id)}
                            onChange={(event) => {
                              tick(row.id, (event.nativeEvent as globalThis.MouseEvent).shiftKey);
                            }}
                          />
                        </td>
                        {columns.map((column, index) => {
                          const cell = (fresh?.cells ?? row.cells)[column.key];
                          // A cell edits in place for a user who may update the row and edit inline.
                          const inline = column.inline === true && row.abilities.update === true && row.abilities.inlineEdit !== false;
                          const url = index === 0 && !inline && clickAction !== 'ignore' ? rowUrl(row) : undefined;

                          return (
                            <td
                              key={column.key}
                              tabIndex={stop(row, index + 1)}
                              // The title cell is the row's link: a click on it, not on a control inside, runs the click action.
                              data-open={url === undefined ? undefined : ''}
                              onClick={
                                url === undefined
                                  ? undefined
                                  : (event) => {
                                      // A click inside a portal, such as a preview, reaches the cell through React's tree only.
                                      if (event.target instanceof Element && event.currentTarget.contains(event.target) && event.target.closest('a, button, input, label, select') === null) {
                                        openRow(event, row);
                                      }
                                    }
                              }
                              className={cn(inline ? cellClasses[index]?.inline : cellClasses[index]?.plain, url !== undefined && 'cursor-pointer hover:underline')}
                              style={cellClasses[index]?.style}
                              onMouseEnter={
                                index === 0
                                  ? () => {
                                      prefetchRow(row);
                                    }
                                  : undefined
                              }
                              onMouseLeave={
                                index === 0
                                  ? () => {
                                      window.clearTimeout(hover.current);
                                    }
                                  : undefined
                              }
                            >
                              {cell !== undefined &&
                                (inline ? (
                                  <InlineCell
                                    uriKey={resource.uriKey}
                                    rowId={row.id}
                                    column={column as unknown as FieldSchema}
                                    cell={cell}
                                    token={fresh === undefined ? row.updatedAt : fresh.updatedAt}
                                    onRow={(cells, updatedAt) => {
                                      // A save answers with the Resource's cells; a lens computes its own, so it takes the edited one only.
                                      const taken = lens == null ? cells : { [column.key]: cells[column.key] ?? cell };
                                      setSaved((current) => ({ ...current, [String(row.id)]: { over: row, cells: { ...row.cells, ...taken }, updatedAt } }));
                                      preview?.saved?.({ ...row, cells: { ...row.cells, ...taken } });
                                    }}
                                  />
                                ) : (
                                  <TableCell column={column} cell={cell} />
                                ))}
                              {index === 0 && row.trashed === true && !trashedOnly && (
                                <span className="ms-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                  {__('Soft Deleted')}
                                </span>
                              )}
                            </td>
                          );
                        })}
                        {withMenu && (
                          <td data-row-menu="" tabIndex={stop(row, columns.length + 1)} className={cn('z-[1] px-3 hover:z-10 focus-within:z-10', menuClass, border, cursorCell)}>
                            <div className="flex items-center justify-end gap-0.5">
                              {row.abilities.view === true && (
                                <Link
                                  href={`${basePath}/resources/${resource.uriKey}/${String(row.id)}`}
                                  aria-label={__('View :title', { title: row.title })}
                                  tabIndex={-1}
                                  className={rowLink}
                                >
                                  <Eye aria-hidden="true" className="size-4" />
                                </Link>
                              )}
                              {row.abilities.update === true && (
                                <Link
                                  href={`${basePath}/resources/${resource.uriKey}/${String(row.id)}/edit`}
                                  aria-label={__('Edit :title', { title: row.title })}
                                  tabIndex={-1}
                                  className={rowLink}
                                >
                                  <Pencil aria-hidden="true" className="size-4" />
                                </Link>
                              )}
                              {hasRowMenu(row) && <RowMenu uriKey={resource.uriKey} row={row} />}
                            </div>
                          </td>
                        )}
                      </tr>
                    )}
                    {subtotal !== undefined && (
                      <GroupSummaryRow
                        columns={columns}
                        values={subtotal}
                        pins={pins}
                        leading={cn(checkboxColumn, border)}
                        trailing={withMenu}
                      />
                    )}
                  </Fragment>
                );
              })}
        </tbody>
        {!loading && summaries === undefined && resource.hasSummaries === true && placeholder}
        {!loading && summaries !== undefined && summaries !== null && (
          // The totals' code loads with their deferred values; until it lands the placeholder holds their place.
          <Suspense fallback={placeholder}>
            <TableSummaryRow
              columns={columns}
              summaries={summaries}
              pins={pins}
              leading={cn(checkboxColumn, border)}
              trailing={withMenu}
            />
          </Suspense>
        )}
      </table>
    </div>
  );
}

/** The Columns button at the header's end; the picker's code loads on the first press, and pointing at the button starts it early. */
function ColumnsButton({ uriKey, table }: { uriKey: string; table: IndexProps['table'] }) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const [trigger, setTrigger] = useState<HTMLButtonElement | null>(null);

  return (
    <div className="relative flex justify-end">
      <button
        ref={setTrigger}
        type="button"
        aria-label={__('Columns')}
        aria-haspopup="dialog"
        aria-expanded={open}
        className="lathe-row-button"
        onPointerEnter={() => {
          void ColumnPicker.preload();
        }}
        onClick={() => {
          setOpen(!open);
        }}
      >
        <Columns3 aria-hidden="true" className="size-4" />
      </button>
      {open && (
        <Suspense fallback={null}>
          <ColumnPicker
            uriKey={uriKey}
            columns={table.allColumns ?? []}
            choice={table.columnChoice ?? null}
            trigger={trigger}
            onClose={() => {
              setOpen(false);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
