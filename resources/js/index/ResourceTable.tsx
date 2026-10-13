import { useTranslation } from '@lathephp/core';
import { router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { Fragment, useState, type MouseEvent } from 'react';
import { cn } from '../lib/cn';
import { TableCell } from '../table/cells';
import type { SharedProps } from '../types';
import { GroupHeaderRow } from './GroupHeaderRow';
import { GroupSummaryRow } from './GroupSummaryRow';
import { menuActions, RowMenu } from './RowMenu';
import { TableSummaryRow } from './TableSummaryRow';
import type { IndexProps, Row } from './types';
import type { RowSelection } from './useRowSelection';

const rowHeights = { compact: 'h-8', comfortable: 'h-10', spacious: 'h-12' };

// Under 768 px the table scrolls inside its card; the checkbox and the first column stay as the row's anchor.
const checkboxColumn = 'sticky start-0 w-10 bg-background px-3';
const firstColumn = 'sticky start-10 bg-background';

// A click on these does its own thing, never the row's click action.
const ownClick = 'a, button, input, label, [data-row-menu]';

interface ResourceTableProps extends Pick<IndexProps, 'resource' | 'table' | 'state'> {
  loading: boolean;
  selection: RowSelection;
  /** The ?sort= of the URL, `-total_cents,placed_at`. */
  sort: string | undefined;
  onSort: (sort: string) => void;
}

/**
 * A click cycles one column through ascending, descending and off. A plain click makes it the
 * only sort; with append the other columns stay and a new one goes last.
 */
function nextSort(sort: string | undefined, key: string, append: boolean): string {
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

function direction(sort: string | undefined, key: string): 'ascending' | 'descending' | 'none' {
  const tokens = (sort ?? '').split(',');

  return tokens.includes(key) ? 'ascending' : tokens.includes(`-${key}`) ? 'descending' : 'none';
}

export function ResourceTable({ resource, table, state, loading, selection, sort, onSort }: ResourceTableProps) {
  const { __ } = useTranslation();
  const { basePath, tableStyle } = usePage<SharedProps>().props;
  const { clickAction, showColumnBorders } = resource.definition;
  // Deferred with the count, so the footer arrives after the rows.
  const { summaries, groups } = usePage<Pick<IndexProps, 'summaries' | 'groups'>>().props;
  // The keys of the groups folded shut.
  const [collapsed, setCollapsed] = useState<string[]>([]);
  const opens = clickAction === 'detail' || clickAction === 'edit' || clickAction === 'select';
  const rowClass = cn(
    'border-b border-border last:border-0',
    // TableStyle tight forces the compact height whatever the panel density.
    rowHeights[resource.definition.tableStyle === 'tight' ? 'compact' : tableStyle.density],
    tableStyle.striped && 'even:bg-muted/50',
  );
  const border = showColumnBorders && 'border-e border-border last:border-e-0';
  const withMenu = table.rows.some((row) => menuActions(row).length > 0);
  const ids = table.rows.map((row) => row.id);
  const ticked = ids.filter((id) => selection.selected.has(id)).length;

  // Edit falls back to detail for a user who may not update the row.
  function rowUrl(row: Row): string | null {
    const detail = `${basePath}/resources/${resource.uriKey}/${String(row.id)}`;

    if (clickAction === 'detail') {
      return detail;
    }

    return clickAction === 'edit' ? (row.abilities.update === true ? `${detail}/edit` : detail) : null;
  }

  function clickRow(event: MouseEvent<HTMLTableRowElement>, row: Row) {
    if (event.target instanceof Element && event.target.closest(ownClick) !== null) {
      return;
    }

    if (clickAction === 'select') {
      selection.toggle(row.id);

      return;
    }

    const url = rowUrl(row);

    if (url === null) {
      return;
    }

    if (event.metaKey || event.ctrlKey) {
      window.open(url, '_blank', 'noopener');
    } else {
      router.visit(url);
    }
  }

  return (
    // Focusable, so a keyboard user can scroll a wide table sideways.
    <div className="overflow-x-auto" role="region" aria-label={__(':resource table', { resource: resource.label })} tabIndex={0}>
      <table className="w-full text-sm">
        <thead>
          <tr className="h-10 border-b border-border text-start text-muted-foreground">
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
            {table.columns.map((column, index) => {
              const sortable = column.sortable && table.sortKeys.includes(column.sortKey);
              const sorted = sortable ? direction(sort, column.sortKey) : 'none';

              return (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={sortable ? sorted : undefined}
                  className={cn('px-3 text-start font-medium whitespace-nowrap', index === 0 && firstColumn, border)}
                >
                  {sortable ? (
                    <button
                      type="button"
                      className="-mx-1 inline-flex items-center gap-1 rounded px-1 font-medium outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                      onClick={(event) => {
                        onSort(nextSort(sort, column.sortKey, event.shiftKey));
                      }}
                    >
                      {column.label}
                      {sorted === 'ascending' && <ArrowUp aria-hidden="true" className="size-3.5" />}
                      {sorted === 'descending' && <ArrowDown aria-hidden="true" className="size-3.5" />}
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              );
            })}
            {withMenu && (
              <th scope="col" className={cn('w-12 px-3', border)}>
                <span className="sr-only">{__('Actions')}</span>
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {loading
            ? Array.from({ length: state.perPage }, (_, index) => (
                <tr key={index} data-skeleton="" aria-hidden="true" className={rowClass}>
                  <td className={cn(checkboxColumn, border)} />
                  {table.columns.map((column) => (
                    <td key={column.key} className={cn('px-3', border)}>
                      <span className="block h-3 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                    </td>
                  ))}
                  {withMenu && <td className={cn(border)} />}
                </tr>
              ))
            : table.rows.map((row, position) => {
                const group = row.group;
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
                        colSpan={table.columns.length + 1 + (withMenu ? 1 : 0)}
                        collapsible={groups?.active?.collapsible ?? false}
                        expanded={!folded}
                        onToggle={() => {
                          setCollapsed(folded ? collapsed.filter((key) => key !== group.key) : [...collapsed, group.key]);
                        }}
                      />
                    )}
                    {!folded && (
                      <tr
                        className={cn(rowClass, opens && 'cursor-pointer hover:bg-muted/50')}
                        onClick={(event) => {
                          clickRow(event, row);
                        }}
                      >
                        <td className={cn(checkboxColumn, border)}>
                          <input
                            type="checkbox"
                            aria-label={__('Select :title', { title: row.title })}
                            className="block size-4"
                            checked={selection.selected.has(row.id)}
                            onChange={() => {
                              selection.toggle(row.id);
                            }}
                          />
                        </td>
                        {table.columns.map((column, index) => {
                          const cell = row.cells[column.key];

                          return (
                            <td key={column.key} className={cn('max-w-80 truncate px-3 whitespace-nowrap', index === 0 && firstColumn, border)}>
                              {cell !== undefined && <TableCell component={column.component} cell={cell} />}
                              {index === 0 && row.trashed === true && (
                                <span className="ms-2 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                                  {__('Soft Deleted')}
                                </span>
                              )}
                            </td>
                          );
                        })}
                        {withMenu && (
                          <td data-row-menu="" className={cn('px-3', border)}>
                            {menuActions(row).length > 0 && <RowMenu uriKey={resource.uriKey} row={row} />}
                          </td>
                        )}
                      </tr>
                    )}
                    {subtotal !== undefined && (
                      <GroupSummaryRow
                        columns={table.columns}
                        values={subtotal}
                        firstColumn={firstColumn}
                        leading={cn(checkboxColumn, border)}
                        trailing={withMenu}
                      />
                    )}
                  </Fragment>
                );
              })}
        </tbody>
        {!loading && summaries !== undefined && summaries !== null && (
          <TableSummaryRow
            columns={table.columns}
            summaries={summaries}
            firstColumn={firstColumn}
            leading={cn(checkboxColumn, border)}
            trailing={withMenu}
          />
        )}
      </table>
    </div>
  );
}
