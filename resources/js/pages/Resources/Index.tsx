import { Head, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { __panel, emit, useShortcut } from '@lathephp/core';
import { AfterDelay, skeletonBar } from '../../components/AfterDelay';
import { Card } from '../../components/ui/Card';
import { EmptyState } from '../../index/EmptyState';
import { IndexBanner } from '../../index/IndexBanner';
import { IndexFooter } from '../../index/IndexFooter';
import { IndexToolbar } from '../../index/IndexToolbar';
import { useLoadMore } from '../../index/Pagination';
import { usePolling, usePollingChoice } from '../../index/PollingToggle';
import { ResourceTable, rowHeights } from '../../index/ResourceTable';
import { SelectionBanner } from '../../index/SelectionBanner';
import { TabBar } from '../../index/TabBar';
import type { IndexProps } from '../../index/types';
import type { IndexUrlState } from '../../index/useIndexState';
import { useIndexState } from '../../index/useIndexState';
import { useRowSelection } from '../../index/useRowSelection';
import type { SharedProps } from '../../types';
import type { WidgetDescriptor } from '../../widgets/types';

// The menus load after the rows, so the action code stays out of the index's first load.
const ActionBar = lazy(() => import('../../actions/ActionBar').then((module) => ({ default: module.ActionBar })));
const DeleteMenu = lazy(() => import('../../actions/DeleteMenu').then((module) => ({ default: module.DeleteMenu })));
// The cards load only when the page lists one, so the grid stays out of the index's first load.
const ResourceWidgets = lazy(() => import('../../widgets/ResourceWidgets').then((module) => ({ default: module.ResourceWidgets })));
const { RenderHook } = __panel;

type IndexPageProps = IndexProps & { widgets?: WidgetDescriptor[] };

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Index(props: IndexPageProps | { title: string; table?: undefined }) {
  return props.table === undefined ? <IndexSkeleton title={props.title} /> : <IndexPage {...props} />;
}

function IndexPage({ resource, table, state, count, error, lens, lenses, actions, widgets = [] }: IndexPageProps) {
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

  // Plugins hear about a filter change through the event bus.
  function visit(changes: IndexUrlState) {
    if ('filter' in changes) {
      emit('filters:changed', { resource: resource.uriKey, filters: changes.filter ?? {} });
    }

    index.visit(changes);
  }

  return (
    <div className="space-y-6">
      <Head title={lens?.name ?? resource.label} />
      <TabBar resource={resource} state={index.state} visit={visit} />
      <IndexToolbar
        resource={resource}
        lens={lens}
        lenses={lenses}
        q={index.state.q}
        visit={visit}
        polling={polling !== null && showPollingToggle ? pollingChoice : null}
      />
      <RenderHook position="index:header-actions" resource={resource.uriKey} className="flex flex-wrap justify-end gap-2" />
      {widgets.length > 0 && (
        <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
          <ResourceWidgets widgets={widgets} path={lens == null ? `resources/${resource.uriKey}` : `resources/${resource.uriKey}/lenses/${lens.uriKey}`} />
        </Suspense>
      )}
      {error !== undefined ? (
        <IndexBanner message={error.message} onReset={index.reset} />
      ) : table.rows.length === 0 ? (
        <EmptyState
          label={resource.label}
          filtered={filtered}
          createHref={resource.abilities.create && lens == null ? `${basePath}/resources/${resource.uriKey}/new` : null}
          onClear={() => {
            visit({ q: undefined, filter: undefined });
          }}
        />
      ) : (
        <Card data-lathe-card className="overflow-hidden">
          {((actions !== undefined && actions.actions.length > 0) || selection.all || selection.selected.size > 0) && (
            // Hidden when the ticked rows allow nothing; while the menus load, a spacer of their
            // height keeps the table from moving under the pointer.
            <div className="flex justify-end gap-2 border-b border-border px-3 py-2 empty:hidden">
              <Suspense fallback={actions !== undefined && actions.actions.length > 0 ? <div aria-hidden="true" className="h-8" /> : null}>
              <DeleteMenu
                uriKey={resource.uriKey}
                lens={lens?.uriKey ?? null}
                softDeletes={resource.softDeletes}
                selection={selection}
                rows={more.rows}
                total={count == null ? null : count.value}
                state={index.state}
              />
              {actions !== undefined && (
                <ActionBar
                  uriKey={resource.uriKey}
                  lens={lens?.uriKey ?? null}
                  list={actions}
                  selection={selection}
                  rows={more.rows}
                  total={count == null ? null : count.value}
                  state={index.state}
                />
              )}
              </Suspense>
            </div>
          )}
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

/** The page before its props arrive: its title, the tab band, the toolbar and ten rows of the table's height. */
function IndexSkeleton({ title }: { title: string }) {
  const { tableStyle } = usePage<SharedProps>().props;

  return (
    <AfterDelay>
      <Head title={title} />
      <div aria-busy="true" className="space-y-6">
        <div aria-hidden="true" className={`h-8 w-64 ${skeletonBar}`} />
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-semibold">{title}</h1>
          <div aria-hidden="true" className={`ms-auto h-9 w-56 ${skeletonBar}`} />
        </div>
        <Card aria-hidden="true" className="overflow-hidden">
          <div className="h-10 border-b border-border" />
          {Array.from({ length: 10 }, (_, index) => (
            <div key={index} data-skeleton-row="" className={`flex items-center border-b border-border px-3 last:border-0 ${rowHeights[tableStyle.density]}`}>
              <span className={`h-3 w-full ${skeletonBar}`} />
            </div>
          ))}
        </Card>
      </div>
    </AfterDelay>
  );
}
