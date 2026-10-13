import { Head, usePage } from '@inertiajs/react';
import { Card } from '@lathephp/ui';
import { EmptyState } from '../../index/EmptyState';
import { IndexBanner } from '../../index/IndexBanner';
import { IndexFooter } from '../../index/IndexFooter';
import { IndexToolbar } from '../../index/IndexToolbar';
import { ResourceTable } from '../../index/ResourceTable';
import type { IndexProps } from '../../index/types';
import { useIndexState } from '../../index/useIndexState';
import type { SharedProps } from '../../types';

export default function Index({ resource, table, state, count, error }: IndexProps) {
  const { basePath } = usePage<SharedProps>().props;
  const index = useIndexState({ perPage: resource.perPageOptions[0] ?? state.perPage });
  const filtered = index.state.q !== undefined || index.state.filter !== undefined;

  return (
    <div className="space-y-6">
      <Head title={resource.label} />
      <IndexToolbar resource={resource} q={index.state.q} visit={index.visit} />
      {error !== undefined ? (
        <IndexBanner message={error.message} onReset={index.reset} />
      ) : table.rows.length === 0 ? (
        <EmptyState
          label={resource.label}
          filtered={filtered}
          createHref={filtered && resource.abilities.create ?`${basePath}/resources/${resource.uriKey}/new` : null}
          onClear={() => {
            index.visit({ q: undefined, filter: undefined });
          }}
        />
      ) : (
        <Card className="overflow-hidden">
          <ResourceTable
            resource={resource}
            table={table}
            state={state}
            loading={index.loading}
            sort={index.state.sort}
            onSort={(sort) => {
              index.visit({ sort });
            }}
          />
          <IndexFooter resource={resource} state={state} count={count} rowCount={table.rows.length} visit={index.visit} />
        </Card>
      )}
    </div>
  );
}
