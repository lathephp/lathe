import { ChevronDown } from 'lucide-react';
import { createContext, useContext } from 'react';

/** Set by an inline cell, so a badge in it carries the chevron inside itself. */
export const InlineChevronContext = createContext(false);

/** The cue that a cell edits in place; CSS shows it on the cell's hover and cursor. */
export function InlineChevron({ className }: { className?: string }) {
  return (
    <span data-inline-chevron="" className={className}>
      <ChevronDown aria-hidden="true" className="size-3" />
    </span>
  );
}

/** The chevron a badge draws inside itself, only in an inline cell. */
export function BadgeChevron() {
  return useContext(InlineChevronContext) ? <InlineChevron className="-me-0.5 inline-flex" /> : null;
}
