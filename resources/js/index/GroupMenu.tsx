import { useTranslation } from '@lathephp/core';
import { usePage } from '@inertiajs/react';
import type { IndexProps } from './types';
import type { IndexUrlState } from './useIndexState';

/** The ?group= that turns grouping off, the Resource's default group included. */
const ungrouped = '_none';

interface GroupMenuProps {
  visit: (changes: IndexUrlState) => void;
}

/** Picks a declared group and its direction, or None. */
export function GroupMenu({ visit }: GroupMenuProps) {
  const { __ } = useTranslation();
  const { groups } = usePage<Pick<IndexProps, 'groups'>>().props;

  if (groups === undefined || groups.available.length === 0) {
    return null;
  }

  const active = groups.active === null ? ungrouped : `${groups.active.id}:${groups.active.direction}`;

  return (
    <div className="flex items-center gap-2 text-sm">
      <label htmlFor="group-by" className="text-muted-foreground">
        {__('Group')}
      </label>
      <select
        id="group-by"
        value={active}
        className="h-8 rounded-md border border-border bg-background px-2 outline-none focus-visible:ring-2 focus-visible:ring-ring"
        onChange={(event) => {
          visit({ group: event.target.value });
        }}
      >
        <option value={ungrouped}>{__('None')}</option>
        {groups.available.flatMap((group) => [
          <option key={`${group.id}:asc`} value={`${group.id}:asc`}>
            {__(':group ascending', { group: group.label })}
          </option>,
          <option key={`${group.id}:desc`} value={`${group.id}:desc`}>
            {__(':group descending', { group: group.label })}
          </option>,
        ])}
      </select>
    </div>
  );
}
