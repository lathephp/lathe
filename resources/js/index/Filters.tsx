import { usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ListFilter } from 'lucide-react';
import { Suspense, useId, useState } from 'react';
import { Button } from '../components/ui/Button';
import { skeletonBar } from '../lib/instant';
import type { SharedProps } from '../types';
import { activeFilters } from './FilterChips';
import { IndexBanner } from './IndexBanner';
import { countText } from './IndexFooter';
import { FilterPanel } from './lazy';
import type { IndexProps } from './types';
import { type IndexUrlState, parseQuery } from './useIndexState';

/** The toolbar's Filter button, its popover, and the banner for a link with removed filters. */
export function Filters({ visit }: { visit: (changes: IndexUrlState) => void }) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource, filters, staleFilters = [], basePath, count } = page.props;
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const state = parseQuery(page.url.split('?')[1] ?? '');
  const filter = activeFilters(page.url, staleFilters);
  const active = Object.keys(filter).length;
  const available = filters?.available ?? [];
  const softDeletes = resource.softDeletes;

  return (
    <>
      {(available.length > 0 || softDeletes) && (
        <div className="relative">
          <Button
            variant="outline"
            aria-expanded={open}
            aria-controls={open ? panelId : undefined}
            // The panel's code loads on the first press; pointing at the button starts it early.
            onPointerEnter={() => {
              void FilterPanel.preload();
            }}
            onClick={() => {
              setOpen(!open);
            }}
          >
            <ListFilter aria-hidden="true" className="size-4" />
            {__('Filter')}
            {active > 0 && (
              <>
                {' '}
                <span className="inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground tabular-nums">
                  {active}
                </span>
              </>
            )}
          </Button>
          {open && (
            // Absolute, so opening it moves nothing under the toolbar.
            <div
              id={panelId}
              role="dialog"
              aria-label={__('Filters')}
              className="absolute end-0 top-full z-10 mt-1 w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-border bg-background shadow-lg"
            >
              <div className="flex h-11 items-center justify-between border-b border-border px-4">
                <span className="text-sm font-semibold">{__('Filters')}</span>
                {available.length > 0 && (
                  <button
                    type="button"
                    className="rounded-sm text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    onClick={() => {
                      visit({ filter: {} });
                    }}
                  >
                    {__('Reset')}
                  </button>
                )}
              </div>
              <div className="max-h-[60vh] overflow-y-auto p-4">
                <Suspense fallback={null}>
                  <FilterPanel
                    resource={resource.uriKey}
                    endpoint={`${basePath}/api/resources/${resource.uriKey}/filters`}
                    available={available}
                    softDeletes={softDeletes}
                    state={{ ...state, filter }}
                    url={page.url}
                    visit={visit}
                  />
                </Suspense>
              </div>
              {/* The index's own deferred count, which the live visit brings: no request of its own. */}
              {count !== null && (
                <div data-lathe-filter-count="" className="flex h-10 items-center border-t border-border bg-muted px-4 text-sm text-muted-foreground tabular-nums">
                  {count === undefined ? (
                    <span data-placeholder="" aria-hidden="true" className={`h-3 w-24 ${skeletonBar}`} />
                  ) : (
                    __(':count :resource', {
                      count: countText(count),
                      resource: (count.value === 1 && !count.capped && !count.estimated ? resource.singularLabel : resource.label).toLowerCase(),
                    })
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      )}
      {staleFilters.length > 0 && (
        <div className="basis-full">
          <IndexBanner
            message={__('This link is out of date')}
            onReset={() => {
              visit({ filter });
            }}
          />
        </div>
      )}
    </>
  );
}
