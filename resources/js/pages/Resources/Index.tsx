import { Head, usePage } from '@inertiajs/react';
import { lazy, Suspense } from 'react';
import { __panel, emit, useShortcut } from '@lathephp/core';
import { AfterDelay, skeletonBar } from '../../lib/instant';
import { Card } from '../../components/ui/Card';
import { EmptyState } from '../../index/EmptyState';
import { FilterChips } from '../../index/FilterChips';
import { IndexBanner } from '../../index/IndexBanner';
import { IndexFooter } from '../../index/IndexFooter';
import { IndexHeader, IndexToolbar, toolbarRow } from '../../index/IndexToolbar';
import { useLoadMore } from '../../index/Pagination';
import { usePolling, usePollingChoice } from '../../index/PollingToggle';
import { PhoneCards } from '../../index/PhoneCards';
import { ResourceTable, rowHeights } from '../../index/ResourceTable';
import { TabBar, tabBand } from '../../index/TabBar';
import type { IndexProps } from '../../index/types';
import type { IndexUrlState } from '../../index/useIndexState';
import { useIndexState } from '../../index/useIndexState';
import { useRowSelection } from '../../index/useRowSelection';
import type { IndexSkeletonHint, SharedProps } from '../../types';
import type { WidgetDescriptor } from '../../widgets/types';

// The menus load after the rows, so the action code stays out of the index's first load.
const ActionBar = lazy(() => import('../../actions/ActionBar').then((module) => ({ default: module.ActionBar })));
const DeleteMenu = lazy(() => import('../../actions/DeleteMenu').then((module) => ({ default: module.DeleteMenu })));
// The cards load only when the page lists one, so the grid stays out of the index's first load.
const ResourceWidgets = lazy(() => import('../../widgets/ResourceWidgets').then((module) => ({ default: module.ResourceWidgets })));
const { RenderHook } = __panel;

type IndexPageProps = IndexProps & { widgets?: WidgetDescriptor[] };

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Index(props: IndexPageProps | { title: string; skeleton?: IndexSkeletonHint; table?: undefined }) {
  return props.table === undefined ? <IndexSkeleton title={props.title} hint={props.skeleton} /> : <IndexPage {...props} />;
}

function IndexPage({ resource, table, state, count, error, lens, actions, widgets = [] }: IndexPageProps) {
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

  const actionMenus = ((actions !== undefined && actions.actions.length > 0) || selection.all || selection.selected.size > 0) && (
    // Hidden when the ticked rows allow nothing; while the menus load, a spacer of their height
    // keeps the controls beside them from moving.
    <div className="flex items-center gap-2 empty:hidden">
      <Suspense fallback={actions !== undefined && actions.actions.length > 0 ? <div aria-hidden="true" className="cn-button-size-sm" /> : null}>
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
  );

  return (
    <div className="space-y-6">
      <Head title={lens?.name ?? resource.label} />
      <IndexHeader resource={resource} lens={lens} />
      <RenderHook position="index:header-actions" resource={resource.uriKey} className="flex flex-wrap justify-end gap-2" />
      {widgets.length > 0 && (
        <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
          <ResourceWidgets widgets={widgets} path={lens == null ? `resources/${resource.uriKey}` : `resources/${resource.uriKey}/lenses/${lens.uriKey}`} />
        </Suspense>
      )}
      {/* One card: the tab band, the toolbar row (the selection bar while rows are ticked), the filter chips, then the table.
          Nothing clips it, so a popover from its toolbar reaches past a short table. */}
      <Card data-lathe-card>
        <TabBar resource={resource} state={index.state} visit={visit} />
        <IndexToolbar
          resource={resource}
          lens={lens}
          q={index.state.q}
          visit={visit}
          polling={polling !== null && showPollingToggle ? pollingChoice : null}
          selection={selection}
          count={count}
          hasMorePages={more.hasMorePages}
          actions={actionMenus}
        />
        <FilterChips visit={visit} />
        {error !== undefined ? (
          <div className="p-4">
            <IndexBanner message={error.message} onReset={index.reset} />
          </div>
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
          <>
            <PhoneCards resource={resource} table={{ ...table, rows: more.rows }} />
            <ResourceTable
              resource={resource}
              cards={table.phoneCard !== undefined}
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
          </>
        )}
      </Card>
    </div>
  );
}

/**
 * The page before its props arrive: its title, then the card with the tab band when the link says the
 * index has tabs, the toolbar row and ten rows of the table's height, so the rows land in place.
 */
function IndexSkeleton({ title, hint }: { title: string; hint?: IndexSkeletonHint }) {
  const { tableStyle } = usePage<SharedProps>().props;

  return (
    <AfterDelay>
      <Head title={title} />
      <div aria-busy="true" className="space-y-6">
        <div data-lathe-header="">
          <h1 className="text-2xl font-semibold">{title}</h1>
        </div>
        <Card aria-hidden="true" data-lathe-card="" className="overflow-hidden">
          {hint?.tabs === true && (
            <div data-skeleton-tabs="" className={tabBand}>
              <div className={`h-8 w-64 ${skeletonBar}`} />
            </div>
          )}
          <div data-lathe-toolbar="" className={`${toolbarRow} justify-end`}>
            <div className={`h-9 w-96 max-w-full ${skeletonBar}`} />
          </div>
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
