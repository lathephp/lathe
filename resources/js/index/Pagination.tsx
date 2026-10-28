import { useTranslation } from '@lathephp/core';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { Fragment } from 'react';
import { Button } from '../components/ui/Button';
import type { Count, ResourceDefinition } from './types';

interface PaginationProps {
  type: ResourceDefinition['paginationType'];
  page: number;
  perPage?: number;
  /** Undefined while the count loads, null when the Resource counts nothing. */
  count?: Count | null;
  hasMorePages: boolean;
  loadingMore: boolean;
  onPage: (page: number) => void;
  onLoadMore: () => void;
}

export function Pagination({ type, page, perPage = 1, count, hasMorePages, loadingMore, onPage, onLoadMore }: PaginationProps) {
  const { __ } = useTranslation();

  if (type === 'load-more') {
    return (
      hasMorePages && (
        <Button variant="outline" size="sm" disabled={loadingMore} onClick={onLoadMore}>
          {__('Load more')}
        </Button>
      )
    );
  }

  // The last page is known only from an exact count that is not capped or estimated. While the
  // count loads (undefined) Last shows disabled, so the pager keeps its width when the count lands.
  const known = type === 'links' && count != null && !count.capped && !count.estimated;
  const loading = type === 'links' && count === undefined;
  const last = known ? Math.ceil(count.value / perPage) : 0;
  // Without a known last page the window stops at the next page, when there is one.
  const end = known ? last : hasMorePages ? page + 1 : page;
  const pages = type === 'simple' ? [] : [...new Set([1, page - 1, page, page + 1, ...(known ? [last] : [2])])].filter((n) => n >= 1 && n <= end).sort((x, y) => x - y);

  // A phone keeps only previous and next, the ones marked data-step.
  function go(label: string, target: number, disabled: boolean, Icon: typeof ChevronLeft, step?: string) {
    return (
      <Button variant="outline" size="sm" data-step={step} aria-label={label} disabled={disabled} onClick={() => { onPage(target); }}>
        <Icon className="size-3.5" />
      </Button>
    );
  }

  return (
    // One segmented group: the buttons share their borders, as in the tables boards.
    <div className="lathe-pager">
      {go(__('First page'), 1, page <= 1, ChevronsLeft)}
      {go(__('Previous page'), page - 1, page <= 1, ChevronLeft, 'previous')}
      {pages.map((n, index) => (
        <Fragment key={n}>
          {n - (pages[index - 1] ?? n) > 1 && <span aria-hidden>…</span>}
          <Button variant={n === page ? 'default' : 'outline'} size="sm" aria-current={n === page ? 'page' : undefined} onClick={() => { onPage(n); }}>
            {n}
          </Button>
        </Fragment>
      ))}
      {pages.length > 0 && !known && hasMorePages && <span aria-hidden>…</span>}
      {go(__('Next page'), page + 1, !hasMorePages, ChevronRight, 'next')}
      {(known || loading) && go(__('Last page'), last, loading || page >= last, ChevronsRight)}
    </div>
  );
}
