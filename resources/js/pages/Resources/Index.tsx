import { Deferred, router, usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button, Card } from '@lathephp/ui';
import { useState } from 'react';
import { cn } from '../../lib/cn';
import { TableCell, type Cell } from '../../table/cells';
import type { SharedProps } from '../../types';

interface Column {
  key: string;
  label: string;
  component: string;
}

interface Row {
  id: number | string;
  title: string;
  cells: Record<string, Cell | undefined>;
  abilities: Record<string, boolean>;
}

interface IndexProps {
  resource: { uriKey: string; label: string; singularLabel: string; perPageOptions: number[] };
  table: { columns: Column[]; rows: Row[]; rowKey: string };
  state: { page: number; perPage: number; hasMorePages: boolean };
  count?: { count: number; capped: boolean };
}

const rowHeights = { compact: 'h-8', comfortable: 'h-10', spacious: 'h-12' };

// A skeleton shorter than this flickers.
const minimumSkeletonMs = 300;

// Under 768 px the table scrolls inside its card; the first column stays as the row's anchor.
const firstColumn = 'sticky start-0 bg-background';

export default function Index({ resource, table, state, count }: IndexProps) {
  const { __ } = useTranslation();
  const page = usePage<SharedProps>();
  const { density, striped } = page.props.tableStyle;
  const [loading, setLoading] = useState(false);

  function visit(pageNumber: number, perPage: number) {
    let startedAt = 0;

    router.get(
      page.url.split('?')[0] ?? page.url,
      { page: pageNumber, per_page: perPage },
      {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
          startedAt = Date.now();
          setLoading(true);
        },
        onFinish: () => {
          setTimeout(
            () => {
              setLoading(false);
            },
            Math.max(0, minimumSkeletonMs - (Date.now() - startedAt)),
          );
        },
      },
    );
  }

  const from = (state.page - 1) * state.perPage + 1;
  const to = from + table.rows.length - 1;
  const rowClass = cn('border-b border-border last:border-0', rowHeights[density], striped && 'even:bg-muted/50');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold">{resource.label}</h1>
      {table.rows.length === 0 ? (
        <Card className="p-12 text-center text-sm text-muted-foreground">
          <p>{__('No :resource yet', { resource: resource.label.toLowerCase() })}</p>
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
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
                            <td
                              key={column.key}
                              className={cn('max-w-80 truncate px-3 whitespace-nowrap', index === 0 && firstColumn)}
                            >
                              {cell !== undefined && <TableCell component={column.component} cell={cell} />}
                            </td>
                          );
                        })}
                      </tr>
                    ))}
              </tbody>
            </table>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-3 py-2 text-sm">
            <p className="text-muted-foreground">
              <Deferred
                data="count"
                fallback={__('Showing :from to :to', { from, to })}
                // A failed count keeps the rows and shows an em dash for the total.
                rescue={__('Showing :from to :to of :count', { from, to, count: '—' })}
              >
                {count !== undefined &&
                  __('Showing :from to :to of :count', {
                    from,
                    to,
                    count: `${count.count.toLocaleString('en')}${count.capped ? '+' : ''}`,
                  })}
              </Deferred>
            </p>
            <div className="flex items-center gap-2">
              <label htmlFor="per-page" className="text-muted-foreground">
                {__('Per page')}
              </label>
              <select
                id="per-page"
                value={state.perPage}
                className="h-8 rounded-md border border-border bg-background px-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
                onChange={(event) => {
                  visit(1, Number(event.target.value));
                }}
              >
                {resource.perPageOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
              <Button
                variant="outline"
                size="sm"
                disabled={state.page <= 1}
                onClick={() => {
                  visit(state.page - 1, state.perPage);
                }}
              >
                {__('Previous')}
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={!state.hasMorePages}
                onClick={() => {
                  visit(state.page + 1, state.perPage);
                }}
              >
                {__('Next')}
              </Button>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
}
