import { Link, usePage } from '@inertiajs/react';
import { Filters } from './FilterPanel';
import { LensSelector } from './LensSelector';
import { PollingToggle } from './PollingToggle';
import type { IndexProps } from './types';
import { ToolbarSearch } from './ToolbarSearch';
import type { IndexUrlState } from './useIndexState';
import type { SharedProps } from '../types';

interface IndexToolbarProps extends Pick<IndexProps, 'resource' | 'lens' | 'lenses'> {
  q: string | undefined;
  visit: (changes: IndexUrlState) => void;
  /** Null when the Resource shows no polling toggle. */
  polling: { on: boolean; onToggle: () => void } | null;
}

export function IndexToolbar({ resource, lens, lenses, q, visit, polling }: IndexToolbarProps) {
  const { basePath } = usePage<SharedProps>().props;
  const resourcePath = `${basePath}/resources/${resource.uriKey}`;

  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <div>
        {lens != null && (
          <Link href={resourcePath} className="text-sm text-muted-foreground hover:underline">
            {resource.label}
          </Link>
        )}
        <h1 className="text-2xl font-semibold">{lens?.name ?? resource.label}</h1>
      </div>
      <div className="flex flex-wrap items-center gap-3">
        {lenses !== undefined && lenses.length > 0 && (
          <LensSelector resourcePath={resourcePath} label={resource.label} lens={lens ?? null} lenses={lenses} />
        )}
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
        <Filters visit={visit} />
        {polling !== null && <PollingToggle on={polling.on} onToggle={polling.onToggle} />}
      </div>
    </div>
  );
}
