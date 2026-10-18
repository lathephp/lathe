import { usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { ListFilter } from 'lucide-react';
import { Suspense, useId, useState } from 'react';
import { Button } from '../components/ui/Button';
import { Card } from '../components/ui/Card';
import type { SharedProps } from '../types';
import { IndexBanner } from './IndexBanner';
import { FilterPanel } from './lazy';
import type { IndexProps } from './types';
import { type IndexUrlState, parseQuery } from './useIndexState';

/** The toolbar's Filters button, its panel, and the banner for a link with removed filters. */
export function Filters({ visit }: { visit: (changes: IndexUrlState) => void }) {
  const { __ } = useTranslation();
  const page = usePage<IndexProps & SharedProps>();
  const { resource, filters, staleFilters = [], basePath } = page.props;
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const state = parseQuery(page.url.split('?')[1] ?? '');
  const filter = Object.fromEntries(Object.entries(state.filter ?? {}).filter(([key]) => !staleFilters.includes(key)));
  const available = filters?.available ?? [];
  const softDeletes = resource.softDeletes;

  return (
    <>
      {(available.length > 0 || softDeletes) && (
        <Button
          variant="outline"
          aria-expanded={open}
          aria-controls={panelId}
          // The panel's code loads on the first press; pointing at the button starts it early.
          onPointerEnter={() => {
            void FilterPanel.preload();
          }}
          onClick={() => {
            setOpen(!open);
          }}
        >
          <ListFilter aria-hidden="true" className="size-4" />
          {__('Filters')}
        </Button>
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
      {open && (
        <Card id={panelId} className="basis-full p-4">
          {/* A new URL starts a fresh draft from it. */}
          <Suspense fallback={null}>
            <FilterPanel
              key={page.url}
              resource={resource.uriKey}
              endpoint={`${basePath}/api/resources/${resource.uriKey}/filters`}
              available={available}
              softDeletes={softDeletes}
              state={{ ...state, filter }}
              visit={visit}
            />
          </Suspense>
        </Card>
      )}
    </>
  );
}
