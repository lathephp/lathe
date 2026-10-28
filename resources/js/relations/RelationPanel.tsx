import { Link, router } from '@inertiajs/react';
import { ApiError, QueryClientProvider, queryClient, request, toast, useLathe, useQuery, useTranslation } from '@lathephp/core';
import { Button, Card } from '@lathephp/ui';
import { ArrowDown, ArrowUp, ChevronDown, ListFilter, Plus } from 'lucide-react';
import { lazy, Suspense, useId, useRef, useState, type MouseEvent } from 'react';
import { createPortal } from 'react-dom';
import type { FieldSchema, RelationFieldSchema } from '../fields/types';
import { InlineCell } from '../index/InlineCell';
import { FilterPanel } from '../index/FilterPanel';
import { countText } from '../index/IndexFooter';
import { Pagination } from '../index/Pagination';
import { alignClass, direction, nextSort } from '../index/ResourceTable';
import { ToolbarSearch } from '../index/ToolbarSearch';
import type { AvailableFilter } from '../index/types';
import { applyChanges, type IndexUrlState } from '../index/useIndexState';
import { useRowSelection } from '../index/useRowSelection';
import { Modal } from '../components/Modal';
import { Select } from '../components/ui/Select';
import { cn } from '../lib/cn';
import { TableCell } from '../table/cells';
import { useGrid } from '../table/useGrid';
import { AttachRelationModal } from './AttachRelationModal';
import { UpdatePivotModal } from './UpdatePivotModal';
import { useRelationActions } from './useRelationActions';
import { useRelateFlags, useRelationIndex, type RelationRow } from './useRelationIndex';

// The pivot action menu loads only for a panel that offers pivot actions.
const PivotActionMenu = lazy(() => import('../actions/PivotActionMenu').then((module) => ({ default: module.PivotActionMenu })));

/** The relation kinds whose panel creates a child through `via`; a through or pivot relation has no foreign key to lock. */
const creatingKinds = ['hasMany', 'morphMany'];

/** The relation kinds whose rows sit on a join table, so the panel attaches, edits the pivot and detaches. */
const pivotKinds = ['belongsToMany', 'morphToMany', 'morphedByMany'];

// A click on these does its own thing, never the row's link.
const ownClick = 'a, button, input, label, select';

// The cell cursor: the focused cell, outlined inside its edges.
const cursorCell = 'focus:outline-2 focus:-outline-offset-2 focus:outline-ring';

interface RelationPanelProps {
  /** The record whose detail page holds the panel. */
  parent: { uriKey: string; id: string | number };
  field: RelationFieldSchema;
  /**
   * Set when the panel fills a tab of the detail page's main card: the card draws the frame, the
   * tab names the panel, and the panel's buttons go into `actions` at the end of the tab row
   * (null while the tab is not the open one).
   */
  tab?: { actions: HTMLElement | null };
}

/**
 * A relation field's rows on a detail page, read from the sub-resource endpoint. Search, sort,
 * filters and paging change only the request, never the page. A folded panel loads nothing.
 */
export function RelationPanel({ parent, field, tab }: RelationPanelProps) {
  const [open, setOpen] = useState(!field.collapsed);
  const bodyId = useId();

  return (
    // The panel reads through the shared client even where no provider wraps the page.
    <QueryClientProvider client={queryClient}>
      <section aria-label={field.label} className={tab === undefined ? 'space-y-2' : undefined}>
        {open ? (
          <PanelBody parent={parent} field={field} bodyId={bodyId} onToggle={setOpen} tab={tab} />
        ) : (
          <>
            <Heading field={field} open={false} bodyId={bodyId} count={null} onToggle={setOpen} />
            <div id={bodyId} hidden />
          </>
        )}
      </section>
    </QueryClientProvider>
  );
}

function Heading({ field, open, bodyId, count, onToggle }: { field: RelationFieldSchema; open: boolean; bodyId: string; count: string | null; onToggle: (open: boolean) => void }) {
  const badge = count === null ? null : <span className="rounded-full bg-muted px-2 text-xs font-medium text-muted-foreground">{count}</span>;

  return (
    <h2 className="flex items-center gap-2 text-base font-semibold">
      {field.collapsible ? (
        <button
          type="button"
          aria-expanded={open}
          aria-controls={bodyId}
          className="flex items-center gap-1"
          onClick={() => {
            onToggle(!open);
          }}
        >
          <ChevronDown aria-hidden="true" className={cn('size-4 transition-transform', !open && '-rotate-90 rtl:rotate-90')} />
          {field.label}
        </button>
      ) : (
        field.label
      )}
      {badge}
    </h2>
  );
}

function PanelBody({ parent, field, bodyId, onToggle, tab }: RelationPanelProps & { bodyId: string; onToggle: (open: boolean) => void }) {
  const { __ } = useTranslation();
  const { basePath, apiPath } = useLathe();
  const [state, setState] = useState<IndexUrlState>({});
  const [filtersOpen, setFiltersOpen] = useState(false);
  const perPageId = useId();
  const filtersId = useId();
  const detachTitleId = useId();
  const [attaching, setAttaching] = useState(false);
  const [editing, setEditing] = useState<RelationRow | null>(null);
  const [detaching, setDetaching] = useState<RelationRow | null>(null);
  const actions = useRelationActions(parent.uriKey, parent.id, field.relationship);
  const selection = useRowSelection();
  const grid = useRef<HTMLTableElement>(null);
  useGrid(grid);
  // Cells and tokens an inline save sent back, by related record, over the row the panel had loaded; a reload's newer row wins.
  const [saved, setSaved] = useState<Record<string, { over: RelationRow; cells: RelationRow['cells']; updatedAt: string | null | undefined }>>({});
  const index = useRelationIndex(parent.uriKey, parent.id, field.relationship, state);
  const flags = useRelateFlags(parent.uriKey, parent.id);
  const filters = useQuery({
    queryKey: ['filters', field.resource],
    queryFn: () => request<{ filters: AvailableFilter[] }>(`${apiPath}/resources/${field.resource}/filters`),
  });
  const data = index.data ?? null;
  const rows = data?.rows ?? [];
  const available = filters.data?.filters ?? [];
  const filtered = (state.q ?? '') !== '' || Object.keys(state.filter ?? {}).length > 0;
  const empty = data !== null && rows.length === 0;
  const canCreate = creatingKinds.includes(field.kind) && flags.data?.[field.relationship]?.add === true;
  const pivoted = pivotKinds.includes(field.kind);
  const canAttach = pivoted && flags.data?.[field.relationship]?.attachAny === true;
  // Editing a pivot row is an attach ability on the server, so it shares the attach flag.
  const canEditPivot = pivoted && flags.data?.[field.relationship]?.attach === true;
  const canDetach = pivoted && flags.data?.[field.relationship]?.detach === true;
  const rowActions = canEditPivot || canDetach;
  const createHref = `${basePath}/resources/${field.resource}/new?via=${parent.uriKey}/${String(parent.id)}/${field.relationship}`;
  const ids = rows.map((row) => row.id);
  const ticked = ids.filter((id) => selection.selected.has(id)).length;
  const count = field.withCount && data?.count != null ? countText(data.count) : null;

  function change(changes: IndexUrlState) {
    setState(applyChanges(state, changes));
  }

  function rowUrl(row: RelationRow): string {
    return `${basePath}/resources/${field.resource}/${String(row.related_id)}`;
  }

  // The title cell opens the related record; a click on a control inside it, or in a portal, does its own thing.
  function clickRow(event: MouseEvent<HTMLTableCellElement>, row: RelationRow) {
    if (!(event.target instanceof Element) || !event.currentTarget.contains(event.target) || event.target.closest(ownClick) !== null) {
      return;
    }

    if (event.metaKey || event.ctrlKey) {
      window.open(rowUrl(row), '_blank', 'noopener');
    } else {
      router.visit(rowUrl(row));
    }
  }

  async function detach(row: RelationRow) {
    setDetaching(null);

    try {
      await actions.detach(row.related_id, row.pivot_id);
      toast.success(__(':title was detached.', { title: row.title }));
    } catch (failure) {
      const message = failure instanceof ApiError && failure.status === 403 ? (failure.data as { message?: string } | null)?.message : undefined;
      toast.error(message ?? __(':title could not be detached.', { title: row.title }));
    }
  }

  const create = (
    <Link
      href={createHref}
      className={
        tab === undefined
          ? 'inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring'
          : 'inline-flex h-7 items-center gap-1 rounded-md border border-border bg-card px-2.5 text-[13px] font-medium outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-focus'
      }
    >
      {tab === undefined ? null : <Plus aria-hidden="true" className="size-3.5" />}
      {__('Create')}
    </Link>
  );
  const buttons = (
    <>
      {canCreate && !(empty && !filtered) ? create : null}
      {canAttach ? (
        <Button
          size={tab === undefined ? 'default' : 'sm'}
          onClick={() => {
            setAttaching(true);
          }}
        >
          {__('Attach')}
        </Button>
      ) : null}
    </>
  );
  // Inside a tab the card and the tab row frame the table; the heading stays for screen readers.
  const Frame = tab === undefined ? Card : 'div';

  return (
    <>
      {tab === undefined ? (
        <div className="flex flex-wrap items-center justify-between gap-2">
          <Heading field={field} open bodyId={bodyId} count={count} onToggle={onToggle} />
          {buttons}
        </div>
      ) : (
        <>
          <div className="sr-only">
            <Heading field={{ ...field, collapsible: false }} open bodyId={bodyId} count={count} onToggle={onToggle} />
          </div>
          {tab.actions === null ? null : createPortal(buttons, tab.actions)}
        </>
      )}
      <Frame id={bodyId} className="overflow-hidden">
        {(data?.searchable === true || available.length > 0 || (data?.pivotActions?.length ?? 0) > 0) && (
          <div className="flex flex-wrap items-center gap-2 border-b border-border p-3">
            {data?.searchable === true && (
              <ToolbarSearch
                label={field.label}
                value={state.q ?? ''}
                debounce={0.3}
                onSearch={(q) => {
                  change({ q });
                }}
              />
            )}
            {available.length > 0 && (
              <Button
                variant="outline"
                aria-expanded={filtersOpen}
                aria-controls={filtersId}
                onClick={() => {
                  setFiltersOpen(!filtersOpen);
                }}
              >
                <ListFilter aria-hidden="true" className="size-4" />
                {__('Filters')}
              </Button>
            )}
            {(data?.pivotActions?.length ?? 0) > 0 && (
              <Suspense fallback={null}>
                <PivotActionMenu
                  resource={field.resource}
                  via={`${parent.uriKey}/${String(parent.id)}/${field.relationship}`}
                  actions={data?.pivotActions ?? []}
                  ids={[...selection.selected]}
                  queryKey={['relation', parent.uriKey, String(parent.id), field.relationship]}
                />
              </Suspense>
            )}
            {filtersOpen && available.length > 0 && (
              <div id={filtersId} className="basis-full pt-2">
                <FilterPanel
                  resource={field.resource}
                  endpoint={`${apiPath}/resources/${field.resource}/filters`}
                  available={available}
                  softDeletes={false}
                  state={state}
                  visit={change}
                />
              </div>
            )}
          </div>
        )}
        {empty ? (
          <div className="space-y-4 p-12 text-center text-sm text-muted-foreground">
            <p>{filtered ? __('No results match your filters') : __('No :resource yet', { resource: field.label })}</p>
            {canCreate && !filtered ? create : null}
          </div>
        ) : (
          <div className="relative overflow-x-auto" role="region" aria-label={__(':resource table', { resource: field.label })}>
            {/* A grid of one tab stop, as on the Resource's own list. */}
            <table ref={grid} role="grid" aria-rowcount={-1} data-lathe-table className="w-full text-sm [&_tr>*:first-child]:ps-5 [&_tr>*:last-child]:pe-5">
              <thead>
                <tr className="h-10 border-b border-border bg-muted text-start text-[11px] font-semibold tracking-[0.04em] text-muted-foreground uppercase">
                  <th scope="col" className="w-10 px-3">
                    <input
                      type="checkbox"
                      aria-label={__('Select all')}
                      className="block size-4"
                      checked={ids.length > 0 && ticked === ids.length}
                      ref={(input) => {
                        if (input !== null) {
                          input.indeterminate = ticked > 0 && ticked < ids.length;
                        }
                      }}
                      onChange={() => {
                        selection.toggleAll(ids);
                      }}
                    />
                  </th>
                  {(data?.columns ?? []).map((column) => {
                    const sortable = column.sortable && data?.sortKeys.includes(column.sortKey) === true;
                    const sorted = sortable ? direction(state.sort, column.sortKey) : 'none';

                    return (
                      <th key={column.key} scope="col" aria-sort={sortable ? sorted : undefined} className={cn('px-3 whitespace-nowrap', alignClass(column))}>
                        {sortable ? (
                          <button
                            type="button"
                            className="-mx-1 inline-flex items-center gap-1 rounded px-1 uppercase outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring"
                            onClick={() => {
                              change({ sort: nextSort(state.sort, column.sortKey, false) });
                            }}
                          >
                            {column.label}
                            {sorted === 'ascending' && <ArrowUp aria-hidden="true" className="size-3.5" />}
                            {sorted === 'descending' && <ArrowDown aria-hidden="true" className="size-3.5" />}
                          </button>
                        ) : (
                          column.label
                        )}
                      </th>
                    );
                  })}
                  {rowActions ? (
                    <th scope="col" className="px-3">
                      <span className="sr-only">{__('Actions')}</span>
                    </th>
                  ) : null}
                </tr>
              </thead>
              <tbody>
                {rows.map((row, position) => (
                  <tr key={row.id} data-row="" className="h-12 border-b border-border last:border-0">
                    <td tabIndex={-1} className={cn('w-10 px-3', cursorCell)}>
                      <input
                        type="checkbox"
                        tabIndex={-1}
                        aria-label={__('Select :title', { title: row.title })}
                        className="block size-4"
                        checked={selection.selected.has(row.id)}
                        onChange={() => {
                          selection.toggle(row.id);
                        }}
                      />
                    </td>
                    {(data?.columns ?? []).map((column, index) => {
                      const kept = saved[String(row.related_id)];
                      const fresh = kept?.over === row ? kept : undefined;
                      const cell = (fresh?.cells ?? row.cells)[column.key];
                      // A cell edits in place, against the related record, for a user who may update it and edit inline.
                      const inline = column.inline === true && row.abilities.update === true && row.abilities.inlineEdit !== false;
                      const opens = index === 0 && !inline;

                      return (
                        <td
                          key={column.key}
                          tabIndex={position === 0 && index === 0 ? 0 : -1}
                          data-open={opens ? '' : undefined}
                          onClick={
                            opens
                              ? (event) => {
                                  clickRow(event, row);
                                }
                              : undefined
                          }
                          className={cn('max-w-80 px-3 whitespace-nowrap', !inline && 'truncate', opens && 'cursor-pointer hover:underline', alignClass(column), cursorCell)}
                        >
                          {cell !== undefined &&
                            (inline ? (
                              <InlineCell
                                uriKey={field.resource}
                                rowId={row.related_id}
                                column={column as unknown as FieldSchema}
                                cell={cell}
                                token={fresh === undefined ? row.updatedAt : fresh.updatedAt}
                                onRow={(cells, updatedAt) => {
                                  setSaved((current) => ({ ...current, [String(row.related_id)]: { over: row, cells: { ...row.cells, ...cells }, updatedAt } }));
                                }}
                              />
                            ) : (
                              <TableCell column={column} cell={cell} />
                            ))}
                        </td>
                      );
                    })}
                    {rowActions ? (
                      <td tabIndex={-1} className={cn('px-3 text-end whitespace-nowrap', cursorCell)}>
                        {canEditPivot ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            tabIndex={-1}
                            onClick={() => {
                              setEditing(row);
                            }}
                          >
                            {__('Edit pivot')}
                          </Button>
                        ) : null}
                        {canDetach ? (
                          <Button
                            variant="ghost"
                            size="sm"
                            tabIndex={-1}
                            className="text-destructive"
                            onClick={() => {
                              setDetaching(row);
                            }}
                          >
                            {__('Detach')}
                          </Button>
                        ) : null}
                      </td>
                    ) : null}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {data !== null && (
          <div className="flex flex-wrap items-center justify-end gap-2 border-t border-border px-3 py-2 text-sm">
            <label htmlFor={perPageId} className="text-muted-foreground">
              {__('Per page')}
            </label>
            <Select
              id={perPageId}
              size="sm"
              value={String(data.perPage)}
              options={data.perPageOptions.map((option) => ({ value: String(option), label: String(option) }))}
              onChange={(option) => {
                change({ per_page: Number(option) });
              }}
            />
            <Pagination
              type="simple"
              page={data.page}
              hasMorePages={data.hasMore}
              loadingMore={false}
              onPage={(page) => {
                change({ page });
              }}
              onLoadMore={() => undefined}
            />
          </div>
        )}
      </Frame>
      {attaching ? (
        <AttachRelationModal
          parent={parent}
          field={field}
          onClose={() => {
            setAttaching(false);
          }}
        />
      ) : null}
      {editing === null ? null : (
        <UpdatePivotModal
          parent={parent}
          field={field}
          row={editing}
          onClose={() => {
            setEditing(null);
          }}
        />
      )}
      {detaching === null ? null : (
        <Modal
          labelledBy={detachTitleId}
          onClose={() => {
            setDetaching(null);
          }}
        >
          <h2 id={detachTitleId} className="text-base font-semibold">
            {__('Detach :title?', { title: detaching.title })}
          </h2>
          <div className="flex justify-end gap-2">
            <Button
              variant="outline"
              onClick={() => {
                setDetaching(null);
              }}
            >
              {__('Cancel')}
            </Button>
            <Button
              variant="destructive-solid"
              onClick={() => {
                void detach(detaching);
              }}
            >
              {__('Detach')}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
