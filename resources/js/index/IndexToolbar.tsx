import { Link, router, usePage } from '@inertiajs/react';
import { shortcutsEnabled, useShortcut, useTranslation } from '@lathephp/core';
import { Filters } from './FilterPanel';
import { GroupMenu } from './GroupMenu';
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
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const resourcePath = `${basePath}/resources/${resource.uriKey}`;
  const canCreate = resource.abilities.create && lens == null;

  useShortcut(
    'c',
    () => {
      router.visit(`${resourcePath}/new`);
    },
    canCreate,
  );

  return (
    <div data-lathe-toolbar className="flex flex-wrap items-center justify-between gap-3">
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
        <GroupMenu visit={visit} />
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
      </div>
    </div>
  );
}
