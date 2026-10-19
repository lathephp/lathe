import { usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ChevronDown, Funnel } from 'lucide-react';
import { Suspense, useEffect, useId, useRef, useState } from 'react';
import { Button } from '../components/ui/Button';
import { skeletonBar } from '../lib/instant';
import type { SharedProps } from '../types';
import { activeFilters, cancelFilterVisit } from './FilterChips';
import { IndexBanner } from './IndexBanner';
import { countText } from './IndexFooter';
import { FilterPanel } from './lazy';
import type { Count, IndexProps } from './types';
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
  const box = useRef<HTMLDivElement>(null);
  const matches = (found: Count) => ({
    count: countText(found),
    resource: (found.value === 1 && !found.capped && !found.estimated ? resource.singularLabel : resource.label).toLowerCase(),
  });

  // Escape or a press outside closes the popover. A select's list draws in a portal, so a press in a listbox stays inside.
  useEffect(() => {
    if (!open) {
      return;
    }

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };
    const onPress = (event: PointerEvent) => {
      const target = event.target instanceof Element ? event.target : null;

      if (target !== null && box.current?.contains(target) !== true && target.closest('[role="listbox"]') === null) {
        setOpen(false);
      }
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('pointerdown', onPress);

    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('pointerdown', onPress);
    };
  }, [open]);

  return (
    <>
      {(available.length > 0 || softDeletes) && (
        <div ref={box} className="relative">
          <Button
            variant="ghost"
            size="sm"
            className="lathe-filter"
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
            <Funnel aria-hidden="true" className="size-3.5" />
            {__('Filter')}
            {active > 0 && (
              <>
                {' '}
                <span className="inline-flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground tabular-nums">
                  {active}
                </span>
              </>
            )}
            <ChevronDown aria-hidden="true" className="size-3.5" />
          </Button>
          {open && (
            <button
              type="button"
              tabIndex={-1}
              aria-label={__('Close')}
              className="fixed inset-0 z-40 bg-scrim md:hidden"
              onClick={() => {
                setOpen(false);
              }}
            />
          )}
          {open && (
            // Absolute, so opening it moves nothing under the toolbar.
            <div
              id={panelId}
              role="dialog"
              aria-label={__('Filters')}
              // Below 768 px a bottom sheet over a scrim, its foot a button that closes it.
              className="absolute end-0 top-full z-10 mt-1 w-[340px] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-border bg-background shadow-lg max-md:fixed max-md:inset-x-0 max-md:top-auto max-md:bottom-0 max-md:z-50 max-md:w-auto max-md:max-w-none max-md:rounded-b-none max-md:rounded-t-2xl"
            >
              <div className="flex h-11 items-center justify-between border-b border-border px-4">
                <span className="text-sm font-semibold">{__('Filters')}</span>
                {available.length > 0 && (
                  <button
                    type="button"
                    className="rounded-sm text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
                    onClick={() => {
                      cancelFilterVisit();
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
                <div data-lathe-filter-count="" className="flex h-10 items-center border-t border-border bg-muted px-4 text-sm text-muted-foreground tabular-nums max-md:h-auto max-md:py-3">
                  {count === undefined ? (
                    <span data-placeholder="" aria-hidden="true" className={`h-3 w-24 ${skeletonBar}`} />
                  ) : (
                    <>
                      <span className="max-md:hidden">{__(':count :resource', matches(count))}</span>
                      <Button
                        className="w-full md:hidden"
                        onClick={() => {
                          setOpen(false);
                        }}
                      >
                        {__('Show :count :resource', matches(count))}
                      </Button>
                    </>
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
