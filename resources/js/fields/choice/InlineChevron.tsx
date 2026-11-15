import { ChevronDown } from 'lucide-react';
import { createContext, useContext } from 'react';

/** Set by an inline cell, so a badge in it carries the chevron inside itself; 'saving' while its save runs. */
export const InlineChevronContext = createContext<false | 'idle' | 'saving'>(false);

/** The cue that a cell edits in place; CSS shows it on the cell's hover and cursor. */
export function InlineChevron({ className }: { className?: string }) {
  // A save spins in the chevron's 12 px box, so the cell keeps its width; CSS shows it only past 300 ms.
  if (useContext(InlineChevronContext) === 'saving') {
    return (
      <span data-inline-saving="" aria-hidden="true" className={className}>
        <span className="size-3 animate-spin rounded-full border-[1.5px] border-muted-foreground border-t-transparent" />
      </span>
    );
  }

  return (
    <span data-inline-chevron="" className={className}>
      <ChevronDown aria-hidden="true" className="size-3" />
    </span>
  );
}

/** The chevron a badge draws inside itself, only in an inline cell. */
export function BadgeChevron() {
  return useContext(InlineChevronContext) === false ? null : <InlineChevron className="-me-0.5 inline-flex" />;
}
