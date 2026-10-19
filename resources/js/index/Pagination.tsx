import { usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { Fragment, useState } from 'react';
import { Button } from '../components/ui/Button';
import type { SharedProps } from '../types';
import type { Count, IndexProps, ResourceDefinition, Row } from './types';

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

interface More {
  /** The table these rows follow; a visit that replaces it drops them. */
  after: IndexProps['table'];
  rows: Row[];
  page: number;
  hasMorePages: boolean;
}

/**
 * Load-more pagination: the next page comes from the JSON index with the same query and is
 * appended below the rows, so the URL keeps the first page.
 */
export function useLoadMore(uriKey: string, table: IndexProps['table'], state: IndexProps['state']) {
  const { __ } = useTranslation();
  const page = usePage<SharedProps>();
  const [more, setMore] = useState<More | null>(null);
  const [loading, setLoading] = useState(false);
  const current = more?.after === table ? more : null;

  async function loadMore() {
    const params = new URLSearchParams(page.url.split('?')[1] ?? '');
    params.set('page', String((current?.page ?? state.page) + 1));
    setLoading(true);

    try {
      const next = await request<Pick<IndexProps, 'table' | 'state'>>(`${page.props.basePath}/api/resources/${uriKey}?${params.toString()}`, { toastOnError: false });

      if (next !== null) {
        setMore({
          after: table,
          rows: [...(current?.rows ?? []), ...next.table.rows],
          page: next.state.page,
          hasMorePages: next.state.hasMorePages,
        });
      }
    } catch {
      toast.error(__('The next rows could not be loaded.'));
    } finally {
      setLoading(false);
    }
  }

  return {
    rows: current === null ? table.rows : [...table.rows, ...current.rows],
    hasMorePages: current?.hasMorePages ?? state.hasMorePages,
    loading,
    loadMore,
  };
}
