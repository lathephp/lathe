import { useTranslation } from '@lathephp/core';
import { usePage } from '@inertiajs/react';
import { Select } from '../components/ui/Select';
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
      <Select
        id="group-by"
        size="sm"
        value={active}
        options={[
          { value: ungrouped, label: __('None') },
          ...groups.available.flatMap((group) => [
            { value: `${group.id}:asc`, label: __(':group ascending', { group: group.label }) },
            { value: `${group.id}:desc`, label: __(':group descending', { group: group.label }) },
          ]),
        ]}
        onChange={(group) => {
          visit({ group });
        }}
      />
    </div>
  );
}
