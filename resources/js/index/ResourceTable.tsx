import { useTranslation } from '@lathephp/core';
import { usePage } from '@inertiajs/react';
import { cn } from '../lib/cn';
import { TableCell } from '../table/cells';
import type { SharedProps } from '../types';
import type { IndexProps } from './types';

const rowHeights = { compact: 'h-8', comfortable: 'h-10', spacious: 'h-12' };

// Under 768 px the table scrolls inside its card; the first column stays as the row's anchor.
const firstColumn = 'sticky start-0 bg-background';

interface ResourceTableProps extends Pick<IndexProps, 'resource' | 'table' | 'state'> {
  loading: boolean;
}

export function ResourceTable({ resource, table, state, loading }: ResourceTableProps) {
  const { __ } = useTranslation();
  const { density, striped } = usePage<SharedProps>().props.tableStyle;
  const rowClass = cn('border-b border-border last:border-0', rowHeights[density], striped && 'even:bg-muted/50');

  return (
    // Focusable, so a keyboard user can scroll a wide table sideways.
    <div className="overflow-x-auto" role="region" aria-label={__(':resource table', { resource: resource.label })} tabIndex={0}>
      <table className="w-full text-sm">
        <thead>
          <tr className="h-10 border-b border-border text-start text-muted-foreground">
            {table.columns.map((column, index) => (
              <th
                key={column.key}
                scope="col"
                className={cn('px-3 text-start font-medium whitespace-nowrap', index === 0 && firstColumn)}
              >
                {column.label}
              </th>
            ))}
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
      </table>
    </div>
  );
}
