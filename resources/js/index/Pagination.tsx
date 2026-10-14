import { usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useState } from 'react';
import type { SharedProps } from '../types';
import type { IndexProps, ResourceDefinition, Row } from './types';

interface PaginationProps {
  type: ResourceDefinition['paginationType'];
  page: number;
  hasMorePages: boolean;
  loadingMore: boolean;
  onPage: (page: number) => void;
  onLoadMore: () => void;
}

export function Pagination({ type, page, hasMorePages, loadingMore, onPage, onLoadMore }: PaginationProps) {
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

  return (
    <>
      <Button
        variant="outline"
        size="sm"
        disabled={page <= 1}
        onClick={() => {
          onPage(page - 1);
        }}
      >
        {__('Previous')}
      </Button>
      <Button
        variant="outline"
        size="sm"
        disabled={!hasMorePages}
        onClick={() => {
          onPage(page + 1);
        }}
      >
        {__('Next')}
      </Button>
    </>
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
