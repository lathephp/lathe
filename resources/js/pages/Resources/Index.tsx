import { Head, Link, router, usePage } from '@inertiajs/react';
import { lazy, Suspense, useState } from 'react';
import { __panel, emit, useShortcut, useTranslation } from '@lathephp/core';
import { Plus } from 'lucide-react';
import { AfterDelay, skeletonBar } from '../../lib/instant';
import { Card } from '../../components/ui/Card';
import { EmptyState } from '../../index/EmptyState';
import { IndexBanner } from '../../index/IndexBanner';
import { IndexToolbar, toolbarRow } from '../../index/IndexToolbar';
import { useLoadMore } from '../../index/useLoadMore';
import { usePolling, usePollingChoice } from '../../index/PollingToggle';
import { PhoneCards } from '../../index/PhoneCards';
import { ResourceTable, rowHeights, sameRow } from '../../index/ResourceTable';
import { Board, IndexFooter, PreviewPanel } from '../../index/lazy';
import { useViews } from '../../index/ViewsRail';
import type { IndexProps, Row } from '../../index/types';
import type { IndexUrlState } from '../../index/useIndexState';
import { useIndexState } from '../../index/useIndexState';
import { useRowSelection } from '../../index/useRowSelection';
import { phone, usePreview } from '../../index/usePreview';
import type { IndexSkeletonHint, SharedProps } from '../../types';
import type { WidgetDescriptor } from '../../widgets/types';
import { PageHeader } from '../../layout/PageHeader';

// The menus load after the rows, so the action code stays out of the index's first load.
const ActionBar = lazy(() => import('../../actions/ActionBar').then((module) => ({ default: module.ActionBar })));
const DeleteMenu = lazy(() => import('../../actions/DeleteMenu').then((module) => ({ default: module.DeleteMenu })));
// The cards load only when the page lists one, so the grid stays out of the index's first load.
const ResourceWidgets = lazy(() => import('../../widgets/ResourceWidgets').then((module) => ({ default: module.ResourceWidgets })));
const { RenderHook } = __panel;

// Back and Forward paint the rows the history entry kept, which an inline save, an undo or a
// record page has since changed; the list asks the server for its rows again. Registered here,
// not in an effect: Inertia can announce the restore before the page's effects have run.
router.on('navigate', (event) => {
  if (event.detail.type === 'history' && event.detail.page.component === 'Resources/Index') {
    router.reload({ only: ['table'] });
  }
});

type IndexPageProps = IndexProps & { widgets?: WidgetDescriptor[] };

/** An instant visit renders the page with its title alone, then the server's props arrive. */
export default function Index(props: IndexPageProps | { title: string; skeleton?: IndexSkeletonHint; table?: undefined }) {
  return props.table === undefined ? <IndexSkeleton title={props.title} hint={props.skeleton} /> : <IndexPage {...props} />;
}

function IndexPage({ resource, table, state, count, error, lens, actions, widgets = [] }: IndexPageProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const createHref = resource.abilities.create && lens == null ? `${basePath}/resources/${resource.uriKey}/new` : null;
  // Sentence case: a capitalised word lowers its first letter, an acronym such as API keeps its case.
  const createLabel = __('Create :resource', { resource: resource.singularLabel.replace(/\b[A-Z](?=[a-z])/g, (letter) => letter.toLowerCase()) });
  const index = useIndexState({ perPage: resource.perPageOptions[0] ?? state.perPage });
  const filtered = index.state.q !== undefined || index.state.filter !== undefined;
  const { polling, showPollingToggle } = resource.definition;
  const pollingChoice = usePollingChoice(resource.uriKey);
  const selection = useRowSelection();
  const more = useLoadMore(resource.uriKey, table, state);
  usePolling(polling, !showPollingToggle || pollingChoice.on);
  const ids = more.rows.map((row) => row.id);
  const preview = usePreview(resource.uriKey, table.previewOpen === true, ids[0]);
  // Each row as an inline save left it, over the row the page loaded; a reload's newer row wins.
  const [saved, setSaved] = useState<Record<string, { over: Row; row: Row }>>({});
  const shown = more.rows.find((row) => String(row.id) === preview.id);
  const kept = saved[preview.id ?? ''];
  // No Board on a lens or a phone; the URL's layout wins over the one this user last chose.
  const board = lens == null && !phone() ? table.board : null;
  const layout = index.state.layout === 'board' || index.state.layout === 'table' ? index.state.layout : (table.layout ?? 'table');
  // The Board selects nothing.
  const selects = board == null || layout !== 'board';
  useShortcut(
    'mod+a',
    () => {
      selection.toggleAll(ids);
    },
    selects,
  );
  useShortcut(
    'mod+shift+a',
    () => {
      selection.selectAllMatching();
    },
    selects,
  );

  // Plugins hear about a filter change through the event bus.
  function visit(changes: IndexUrlState) {
    if ('filter' in changes) {
      emit('filters:changed', { resource: resource.uriKey, filters: changes.filter ?? {} });
    }

    index.visit(changes);
  }

  const views = useViews({ resource, state: index.state, visit });

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
            total={count ?? null}
            state={index.state}
          />
        )}
      </Suspense>
    </div>
  );

  // On a phone the card's content runs edge to edge under the top bar, as the mobile board draws it.
  return (
    <div className="lathe-index">
      <Head title={lens?.name ?? resource.label} />
      <PageHeader
        title={lens?.name ?? resource.label}
        actions={
          <>
            <RenderHook position="index:header-actions" resource={resource.uriKey} className="flex gap-2" />
            {createHref !== null && (
              <Link href={createHref} aria-keyshortcuts="c" className="lathe-button lathe-button-default lathe-button-size-default ps-2.5 max-md:hidden">
                <Plus aria-hidden="true" className="size-4" />
                {createLabel}
              </Link>
            )}
          </>
        }
        phoneAction={
          createHref !== null && (
            <Link href={createHref} aria-label={createLabel} className="lathe-button lathe-button-default lathe-page-bar-action">
              <Plus aria-hidden="true" className="size-4" />
            </Link>
          )
        }
      />
      {widgets.length > 0 && (
        <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
          <ResourceWidgets widgets={widgets} path={lens == null ? `resources/${resource.uriKey}` : `resources/${resource.uriKey}/lenses/${lens.uriKey}`} />
        </Suspense>
      )}
      {/* The views rail at the card's left from 1100 px; narrower, or beside an open preview panel, it folds into the toolbar's Views menu. */}
      <div className="lathe-index-body" data-preview-open={preview.id === null ? undefined : ''}>
      {views?.rail}
      {/* At 1280 px and wider an open preview panel sits beside the card; below that it lies over it. */}
      {/* One card: the toolbar row (the selection bar while rows are ticked) with the filter bar, then the table.
          Nothing clips it, so a popover from its toolbar reaches past a short table. */}
      <div className={preview.id === null ? undefined : 'lathe-index-split'}>
        <Card data-lathe-card>
          <IndexToolbar
            views={views?.button}
            resource={resource}
            lens={lens}
            q={index.state.q}
            visit={visit}
            polling={polling !== null && showPollingToggle ? pollingChoice : null}
            selection={selection}
            count={count}
            hasMorePages={more.hasMorePages}
            actions={actionMenus}
            layout={board == null ? null : layout}
          />
          {error !== undefined ? (
            <div className="p-4">
              <IndexBanner message={error.message} onReset={index.reset} />
            </div>
          ) : table.rows.length === 0 ? (
            <EmptyState
              label={resource.label}
              filtered={filtered}
              createHref={createHref}
              onClear={() => {
                visit({ q: undefined, filter: undefined });
              }}
            />
          ) : board != null && layout === 'board' ? (
            <Suspense fallback={<div aria-hidden="true" className="min-h-40" />}>
              <Board
                resource={resource}
                table={table}
                board={board}
                preview={{
                  ...preview,
                  saved: (row) => {
                    setSaved((current) => ({ ...current, [String(row.id)]: { over: row, row } }));
                  },
                }}
              />
            </Suspense>
          ) : (
            <>
              <PhoneCards resource={resource} table={{ ...table, rows: more.rows }} onOpen={preview.show} />
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
                preview={{
                show: preview.show,
                toggle: preview.toggle,
                moved: preview.follow,
                saved: (row) => {
                  const over = more.rows.find((each) => each.id === row.id);

                  if (over !== undefined) {
                    setSaved((current) => ({ ...current, [String(row.id)]: { over, row } }));
                  }
                },
              }}
              />
              <Suspense fallback={<div className="lathe-footer" aria-hidden="true" />}>
                <IndexFooter
                  resource={resource}
                  state={state}
                  count={count}
                  rowCount={more.rows.length}
                  loadedPage={more.page}
                  hasMorePages={more.hasMorePages}
                  loadingMore={more.loading}
                  visit={index.visit}
                  onLoadMore={() => {
                    void more.loadMore();
                  }}
                />
              </Suspense>
            </>
          )}
        </Card>
        {preview.id !== null && (
          <Suspense fallback={null}>
            <PreviewPanel
              resource={resource}
              table={table}
              id={preview.id}
              // A Board card is not one of the page's rows, so its last save shows over whatever the page holds.
              row={board != null && layout === 'board' ? (kept?.row ?? shown) : kept !== undefined && shown !== undefined && sameRow(kept.over, shown) ? kept.row : shown}
              onClose={() => {
                preview.close();
                // The grid's cursor takes the focus back from the panel, or the card it showed on a phone or the Board, where the list stays put.
                const card = phone() ? `[data-lathe-cards] [data-id="${preview.id ?? ''}"]` : board != null && layout === 'board' ? `[data-board-card="${preview.id ?? ''}"]` : '[role=grid] td[tabindex="0"]';
                document.querySelector<HTMLElement>(card)?.focus({ preventScroll: true });
              }}
            />
          </Suspense>
        )}
        </div>
        {views?.overlays}
        </div>
      </div>
    );
  }

  /**
   * The page before its props arrive: its title, the views rail when the link says the index has tabs, then
   * the card with the toolbar row and ten rows of the table's height, so the rows land in place.
   */
  function IndexSkeleton({ title, hint }: { title: string; hint?: IndexSkeletonHint }) {
    const { tableStyle } = usePage<SharedProps>().props;

    return (
      <AfterDelay>
        <Head title={title} />
        <div aria-busy="true" className="space-y-5">
          <PageHeader title={title} />
          <div className="lathe-index-body">
          {hint?.tabs === true && (
            <div aria-hidden="true" data-skeleton-tabs="" className="lathe-views lathe-views-rail w-[220px] shrink-0">
              <div className={`h-32 ${skeletonBar}`} />
            </div>
          )}
          <Card aria-hidden="true" data-lathe-card="" className="overflow-hidden">
            <div data-lathe-toolbar="" className={toolbarRow}>
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
      </div>
    </AfterDelay>
  );
}
