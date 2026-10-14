import { Head, usePage } from '@inertiajs/react';
import { useShortcut } from '@lathephp/core';
import { Card } from '@lathephp/ui';
import { EmptyState } from '../../index/EmptyState';
import { IndexBanner } from '../../index/IndexBanner';
import { IndexFooter } from '../../index/IndexFooter';
import { IndexToolbar } from '../../index/IndexToolbar';
import { useLoadMore } from '../../index/Pagination';
import { usePolling, usePollingChoice } from '../../index/PollingToggle';
import { ResourceTable } from '../../index/ResourceTable';
import { SelectionBanner } from '../../index/SelectionBanner';
import { TabBar } from '../../index/TabBar';
import type { IndexProps } from '../../index/types';
import { useIndexState } from '../../index/useIndexState';
import { useRowSelection } from '../../index/useRowSelection';
import type { SharedProps } from '../../types';

export default function Index({ resource, table, state, count, error, lens, lenses }: IndexProps) {
  const { basePath } = usePage<SharedProps>().props;
  const index = useIndexState({ perPage: resource.perPageOptions[0] ?? state.perPage });
  const filtered = index.state.q !== undefined || index.state.filter !== undefined;
  const { polling, showPollingToggle } = resource.definition;
  const pollingChoice = usePollingChoice(resource.uriKey);
  const selection = useRowSelection();
  const more = useLoadMore(resource.uriKey, table, state);
  usePolling(polling, !showPollingToggle || pollingChoice.on);
  const ids = more.rows.map((row) => row.id);
  useShortcut('mod+a', () => {
    selection.toggleAll(ids);
  });
  useShortcut('mod+shift+a', () => {
    selection.selectAllMatching();
  });

  return (
    <div className="space-y-6">
      <Head title={lens?.name ?? resource.label} />
      <TabBar resource={resource} state={index.state} visit={index.visit} />
      <IndexToolbar
        resource={resource}
        lens={lens}
        lenses={lenses}
        q={index.state.q}
        visit={index.visit}
        polling={polling !== null && showPollingToggle ? pollingChoice : null}
      />
      {error !== undefined ? (
        <IndexBanner message={error.message} onReset={index.reset} />
      ) : table.rows.length === 0 ? (
        <EmptyState
          label={resource.label}
          filtered={filtered}
          createHref={resource.abilities.create && lens == null ? `${basePath}/resources/${resource.uriKey}/new` : null}
          onClear={() => {
            index.visit({ q: undefined, filter: undefined });
          }}
        />
      ) : (
        <Card className="overflow-hidden">
          <SelectionBanner selection={selection} ids={ids} count={count} hasMorePages={more.hasMorePages} />
          <ResourceTable
            resource={resource}
            table={{ ...table, rows: more.rows }}
            state={state}
            loading={index.loading}
            selection={selection}
            sort={index.state.sort}
            onSort={(sort) => {
              index.visit({ sort });
            }}
          />
          <IndexFooter
            resource={resource}
            state={state}
            count={count}
            rowCount={more.rows.length}
            hasMorePages={more.hasMorePages}
            loadingMore={more.loading}
            visit={index.visit}
            onLoadMore={() => {
              void more.loadMore();
            }}
          />
        </Card>
      )}
    </div>
  );
}
