import { useTranslation } from '@lathephp/core';
import { usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { cn } from '../lib/cn';
import { TableCell } from '../table/cells';
import type { SharedProps } from '../types';
import { TableSummaryRow } from './TableSummaryRow';
import type { IndexProps } from './types';

const rowHeights = { compact: 'h-8', comfortable: 'h-10', spacious: 'h-12' };

// Under 768 px the table scrolls inside its card; the first column stays as the row's anchor.
const firstColumn = 'sticky start-0 bg-background';

interface ResourceTableProps extends Pick<IndexProps, 'resource' | 'table' | 'state'> {
  loading: boolean;
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

export function ResourceTable({ resource, table, state, loading, sort, onSort }: ResourceTableProps) {
  const { __ } = useTranslation();
  const { density, striped } = usePage<SharedProps>().props.tableStyle;
  // Deferred with the count, so the footer arrives after the rows.
  const { summaries } = usePage<Pick<IndexProps, 'summaries'>>().props;
  const rowClass = cn('border-b border-border last:border-0', rowHeights[density], striped && 'even:bg-muted/50');

  return (
    // Focusable, so a keyboard user can scroll a wide table sideways.
    <div className="overflow-x-auto" role="region" aria-label={__(':resource table', { resource: resource.label })} tabIndex={0}>
      <table className="w-full text-sm">
        <thead>
          <tr className="h-10 border-b border-border text-start text-muted-foreground">
            {table.columns.map((column, index) => {
              const sortable = column.sortable && table.sortKeys.includes(column.sortKey);
              const sorted = sortable ? direction(sort, column.sortKey) : 'none';

              return (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={sortable ? sorted : undefined}
                  className={cn('px-3 text-start font-medium whitespace-nowrap', index === 0 && firstColumn)}
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
          </tr>
        </thead>
        <tbody>
          {loading
            ? Array.from({ length: state.perPage }, (_, index) => (
                <tr key={index} data-skeleton="" aria-hidden="true" className={rowClass}>
                  {table.columns.map((column) => (
                    <td key={column.key} className="px-3">
                      <span className="block h-3 animate-pulse rounded bg-muted motion-reduce:animate-none" />
                    </td>
                  ))}
                </tr>
              ))
            : table.rows.map((row) => (
                <tr key={row.id} className={rowClass}>
                  {table.columns.map((column, index) => {
                    const cell = row.cells[column.key];

                    return (
                      <td key={column.key} className={cn('max-w-80 truncate px-3 whitespace-nowrap', index === 0 && firstColumn)}>
                        {cell !== undefined && <TableCell component={column.component} cell={cell} />}
                      </td>
                    );
                  })}
                </tr>
              ))}
        </tbody>
        {!loading && summaries !== undefined && summaries !== null && (
          <TableSummaryRow columns={table.columns} summaries={summaries} firstColumn={firstColumn} />
        )}
      </table>
    </div>
  );
}
