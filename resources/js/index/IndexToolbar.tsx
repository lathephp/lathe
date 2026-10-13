import type { IndexProps } from './types';
import { ToolbarSearch } from './ToolbarSearch';
import type { IndexUrlState } from './useIndexState';

interface IndexToolbarProps extends Pick<IndexProps, 'resource'> {
  q: string | undefined;
  visit: (changes: IndexUrlState) => void;
}

export function IndexToolbar({ resource, q, visit }: IndexToolbarProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <h1 className="text-2xl font-semibold">{resource.label}</h1>
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
    </div>
  );
}
