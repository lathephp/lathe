import { Head } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Card } from '@lathephp/ui';
import { IndexFooter } from '../../index/IndexFooter';
import { IndexToolbar } from '../../index/IndexToolbar';
import { ResourceTable } from '../../index/ResourceTable';
import type { IndexProps } from '../../index/types';
import { useIndexState } from '../../index/useIndexState';

export default function Index({ resource, table, state, count }: IndexProps) {
  const { __ } = useTranslation();
  const index = useIndexState({ perPage: resource.perPageOptions[0] ?? state.perPage });

  return (
    <div className="space-y-6">
      <Head title={resource.label} />
      <IndexToolbar resource={resource} />
      {table.rows.length === 0 ? (
        <Card className="p-12 text-center text-sm text-muted-foreground">
          <p>{__('No :resource yet', { resource: resource.label.toLowerCase() })}</p>
        </Card>
      ) : (
        <Card className="overflow-hidden">
          <ResourceTable resource={resource} table={table} state={state} loading={index.loading} />
          <IndexFooter resource={resource} state={state} count={count} rowCount={table.rows.length} visit={index.visit} />
        </Card>
      )}
    </div>
  );
}
