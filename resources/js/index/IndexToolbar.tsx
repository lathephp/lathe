import { router, usePage } from '@inertiajs/react';
import { useShortcut, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '../components/ui/Button';
import { FilterBar } from './FilterBar';
import { activeFilters } from './FilterChips';
import { GroupMenu } from './GroupMenu';
import { IndexBanner } from './IndexBanner';
import { countText } from './IndexFooter';
import { PollingToggle } from './PollingToggle';
import type { Count, IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';
import type { RowSelection } from './useRowSelection';
import type { SharedProps } from '../types';

/** The card's toolbar row; the selection bar takes the same row, so the header row stays put. A narrow window wraps it to a second line. */
export const toolbarRow = 'lathe-toolbar';

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
  /** The Views menu, first in the row; app.css shows it where the views rail folds away. */
  views?: ReactNode;
}

export function IndexToolbar({ resource, lens, q, visit, polling, selection, count, hasMorePages, actions, views }: IndexToolbarProps) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { basePath, staleFilters = [] } = page.props;
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
    <div data-lathe-toolbar="" data-selecting={selecting || undefined} className={toolbarRow}>
      {selecting ? (
        <div role="status" className="lathe-selection">
          <span>
            {selection.all
              ? total === null
                ? __('All matching rows selected')
                : __('All :count selected', { count: total })
              : __(':count selected', { count: selection.selected.size })}
          </span>
          {!selection.all && more && (
            <button type="button" onClick={selection.selectAllMatching}>
              {total === null ? __('Select all matching rows') : __('Select all :count', { count: total })}
            </button>
          )}
        </div>
      ) : (
        <div className="flex flex-wrap items-center gap-1">
          {views}
          {(resource.searchable || resource.softDeletes || (page.props.filters?.available.length ?? 0) > 0) && <FilterBar q={q} visit={visit} />}
          <GroupMenu visit={visit} />
        </div>
      )}
      <div className="ms-auto flex flex-wrap items-center justify-end gap-1">
        {actions}
        {selecting ? (
          <Button variant="ghost" size="sm" onClick={selection.clear}>
            <X aria-hidden="true" className="size-3.5" />
            {__('Clear')}
          </Button>
        ) : (
          polling !== null && <PollingToggle on={polling.on} onToggle={polling.onToggle} />
        )}
      </div>
      {!selecting && staleFilters.length > 0 && (
        <div className="basis-full">
          <IndexBanner
            message={__('This link is out of date')}
            onReset={() => {
              visit({ filter: activeFilters(page.url, staleFilters) });
            }}
          />
        </div>
      )}
    </div>
  );
}
