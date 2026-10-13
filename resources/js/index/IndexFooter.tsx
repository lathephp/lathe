import { Deferred } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import type { Count, IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';

interface IndexFooterProps extends Pick<IndexProps, 'resource' | 'state' | 'count'> {
  rowCount: number;
  visit: (changes: IndexUrlState) => void;
}

// An estimate is the database's guess for the table, so it reads as a rounded figure.
function countText(count: Count): string {
  if (count.estimated) {
    return `~${new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(count.value)}`;
  }

  return `${count.value.toLocaleString('en')}${count.capped ? '+' : ''}`;
}

export function IndexFooter({ resource, state, count, rowCount, visit }: IndexFooterProps) {
  const { __ } = useTranslation();
  const from = (state.page - 1) * state.perPage + 1;
  const to = from + rowCount - 1;

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-3 py-2 text-sm">
      <p className="text-muted-foreground">
        <Deferred
          data="count"
          fallback={__('Showing :from to :to', { from, to })}
          // A failed count keeps the rows and shows an em dash for the total.
          rescue={__('Showing :from to :to of :count', { from, to, count: '—' })}
        >
          {count === null || count === undefined
            ? __('Showing :from to :to', { from, to })
            : __('Showing :from to :to of :count', { from, to, count: countText(count) })}
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
            visit({ per_page: Number(event.target.value) });
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
            visit({ page: state.page - 1 });
          }}
        >
          {__('Previous')}
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={!state.hasMorePages}
          onClick={() => {
            visit({ page: state.page + 1 });
          }}
        >
          {__('Next')}
        </Button>
      </div>
    </div>
  );
}
