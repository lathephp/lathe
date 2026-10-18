import { Link, router, usePage } from '@inertiajs/react';
import { shortcutsEnabled, useShortcut, useTranslation } from '@lathephp/core';
import type { ReactNode } from 'react';
import { Button } from '../components/ui/Button';
import { Filters } from './Filters';
import { GroupMenu } from './GroupMenu';
import { countText } from './IndexFooter';
import { PollingToggle } from './PollingToggle';
import type { Count, IndexProps } from './types';
import { ToolbarSearch } from './ToolbarSearch';
import type { IndexUrlState } from './useIndexState';
import type { RowSelection } from './useRowSelection';
import type { SharedProps } from '../types';

/** The card's toolbar row; the selection bar takes the same row, so the header row below never moves. */
export const toolbarRow = 'flex h-14 items-center gap-2 border-b border-border px-4 max-md:h-auto max-md:min-h-14 max-md:flex-wrap max-md:py-2';

/** The page title, on a row of its own above the table card. */
export function IndexHeader({ resource, lens }: Pick<IndexProps, 'resource' | 'lens'>) {
  const { basePath } = usePage<SharedProps>().props;

  return (
    <div data-lathe-header="">
      {lens != null && (
        <Link href={`${basePath}/resources/${resource.uriKey}`} className="text-sm text-muted-foreground hover:underline">
          {resource.label}
        </Link>
      )}
      <h1 className="text-2xl font-semibold">{lens?.name ?? resource.label}</h1>
    </div>
  );
}

interface IndexToolbarProps extends Pick<IndexProps, 'resource' | 'lens'> {
  q: string | undefined;
  visit: (changes: IndexUrlState) => void;
  /** Null when the Resource shows no polling toggle. */
  polling: { on: boolean; onToggle: () => void } | null;
  selection: RowSelection;
  /** Undefined while the deferred count loads, null when the Resource counts nothing. */
  count: Count | null | undefined;
  hasMorePages: boolean;
  /** The delete and action menus; they keep their place whether rows are ticked or not. */
  actions: ReactNode;
}

export function IndexToolbar({ resource, lens, q, visit, polling, selection, count, hasMorePages, actions }: IndexToolbarProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const resourcePath = `${basePath}/resources/${resource.uriKey}`;
  const canCreate = resource.abilities.create && lens == null;
  const selecting = selection.all || selection.selected.size > 0;
  const total = count == null ? null : countText(count);
  const more = hasMorePages || (count != null && count.value > selection.selected.size);

  useShortcut(
    'c',
    () => {
      router.visit(`${resourcePath}/new`);
    },
    canCreate,
  );

  return (
    <div data-lathe-toolbar="" className={toolbarRow}>
      {selecting ? (
        <div role="status" className="flex flex-wrap items-center gap-3 text-sm">
          <span className="font-semibold tabular-nums">
            {selection.all
              ? total === null
                ? __('All matching rows selected')
                : __('All :count selected', { count: total })
              : __(':count selected', { count: selection.selected.size })}
          </span>
          {!selection.all && more && (
            <button
              type="button"
              className="rounded-sm font-medium text-primary tabular-nums outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
              onClick={selection.selectAllMatching}
            >
              {total === null ? __('Select all matching rows') : __('Select all :count', { count: total })}
            </button>
          )}
        </div>
      ) : (
        <div className="flex flex-wrap items-center gap-3">
          {resource.searchable && (
            <ToolbarSearch
              label={resource.label}
              value={q ?? ''}
              debounce={resource.definition.debounce}
              onSearch={(term) => {
                // An empty term leaves q out of the URL.
                visit({ q: term });
              }}
            />
          )}
          <GroupMenu visit={visit} />
        </div>
      )}
      <div className="ms-auto flex flex-wrap items-center justify-end gap-3">
        {actions}
        {selecting ? (
          <Button variant="ghost" size="sm" onClick={selection.clear}>
            {__('Clear')}
          </Button>
        ) : (
          <>
            <Filters visit={visit} />
            {polling !== null && <PollingToggle on={polling.on} onToggle={polling.onToggle} />}
            {canCreate && (
              <Link
                href={`${resourcePath}/new`}
                aria-keyshortcuts="c"
                className="inline-flex h-9 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
              >
                {__('Create :resource', { resource: resource.singularLabel })}
                {shortcutsEnabled() && (
                  <kbd aria-hidden="true" className="text-xs opacity-80 max-md:hidden">{'c'.toUpperCase()}</kbd>
                )}
              </Link>
            )}
          </>
        )}
      </div>
    </div>
  );
}
