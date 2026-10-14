import { ChevronDown, ChevronRight } from 'lucide-react';
import type { RowGroup } from './types';

interface GroupHeaderRowProps {
  group: RowGroup;
  colSpan: number;
  /** With collapsible, the title is a button that folds the group's rows. */
  collapsible: boolean;
  expanded: boolean;
  onToggle: () => void;
}

/** The row above a group: its title, its description and, when it folds, a chevron. */
export function GroupHeaderRow({ group, colSpan, collapsible, expanded, onToggle }: GroupHeaderRowProps) {
  const Chevron = expanded ? ChevronDown : ChevronRight;

  return (
    <tr className="h-10 border-b border-border bg-muted/50">
      <th scope="colgroup" colSpan={colSpan} className="px-3 text-start font-medium whitespace-nowrap">
        {collapsible ? (
          <button
            type="button"
            aria-expanded={expanded}
            className="-mx-1 inline-flex items-center gap-1 rounded px-1 font-medium outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onClick={onToggle}
          >
            <Chevron aria-hidden="true" className={expanded ? 'size-4' : 'size-4 rtl:rotate-180'} />
            {group.title}
          </button>
        ) : (
          group.title
        )}
        {group.description !== null && <span className="ms-2 font-normal text-muted-foreground">{group.description}</span>}
      </th>
    </tr>
  );
}
