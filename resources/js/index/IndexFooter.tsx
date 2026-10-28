import { Deferred } from '@inertiajs/react';
import { formatNumber, useTranslation } from '@lathephp/core';
import { Select } from '../components/ui/Select';
import { Pagination } from './Pagination';
import type { Count, IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';

interface IndexFooterProps extends Pick<IndexProps, 'resource' | 'state' | 'count'> {
  /** The rows on screen, those load-more appended included. */
  rowCount: number;
  /** The last page load-more appended, or the current page before any load. */
  loadedPage: number;
  hasMorePages: boolean;
  loadingMore: boolean;
  visit: (changes: IndexUrlState) => void;
  onLoadMore: () => void;
}

// An estimate is the database's guess for the table, so it reads as a rounded figure.
export function countText(count: Count): string {
  if (count.estimated) {
    return `~${formatNumber(count.value, { notation: 'compact', maximumFractionDigits: 1 })}`;
  }

  return `${formatNumber(count.value)}${count.capped ? '+' : ''}`;
}

// Load-more stops here and a pager takes over, so the page never holds more rows.
const MAX_ROWS = 100;

export function IndexFooter({ resource, state, count, rowCount, loadedPage, hasMorePages, loadingMore, visit, onLoadMore }: IndexFooterProps) {
  const { __ } = useTranslation();
  const capped = resource.definition.paginationType === 'load-more' && rowCount >= MAX_ROWS && hasMorePages;
  const from = (state.page - 1) * state.perPage + 1;
  const to = from + rowCount - 1;

  return (
    // The pager first, then the page size and the range at the end.
    <div className="lathe-footer">
      <Pagination
        type={capped ? 'simple' : resource.definition.paginationType}
        page={capped ? loadedPage : state.page}
        perPage={state.perPage}
        count={count}
        hasMorePages={hasMorePages}
        loadingMore={loadingMore}
        onPage={(page) => {
          visit({ page });
        }}
        onLoadMore={onLoadMore}
      />
      <div className="ms-auto flex flex-wrap items-center gap-4">
        <div className="flex items-center gap-2">
          <label htmlFor="per-page">{__('Per page')}</label>
          <Select
            id="per-page"
            size="sm"
            value={String(state.perPage)}
            options={resource.perPageOptions.map((option) => ({ value: String(option), label: String(option) }))}
            onChange={(option) => {
              visit({ per_page: Number(option) });
            }}
          />
        </div>
        <p className="tabular-nums">
          {/* Simple pagination counts nothing, so it shows the range alone. */}
          {resource.definition.paginationType === 'simple' ? (
            __(':from–:to', { from, to })
          ) : (
            <Deferred
              data="count"
              fallback={__(':from–:to', { from, to })}
              // A failed count keeps the rows and shows an em dash for the total.
              rescue={__(':from–:to of :count', { from, to, count: '—' })}
            >
              {count === null || count === undefined ? __(':from–:to', { from, to }) : __(':from–:to of :count', { from, to, count: countText(count) })}
            </Deferred>
          )}
        </p>
      </div>
    </div>
  );
}
