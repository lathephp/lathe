import { useTranslation } from '@lathephp/core';
import { useId, useState, type ReactNode } from 'react';

/** A value with more lines than this collapses behind "Show Content". */
export const COLLAPSE_LINES = 10;

/** The number of lines in a text. */
export function lineCount(text: string): number {
  return text === '' ? 0 : text.split('\n').length;
}

interface CollapsibleProps {
  /** How many lines the content has. */
  lines: number;
  /** Never collapse: `alwaysShow()` or a `shouldShow` answer of true. */
  open?: boolean;
  children: ReactNode;
}

/** Content that clips behind a Show Content button once it passes 10 lines. */
export function Collapsible({ lines, open = false, children }: CollapsibleProps) {
  const { __ } = useTranslation();
  const [expanded, setExpanded] = useState(false);
  const id = useId();

  if (open || lines <= COLLAPSE_LINES) {
    return <>{children}</>;
  }

  return (
    <div>
      <div id={id} data-collapsed={!expanded || undefined} className={expanded ? undefined : 'max-h-60 overflow-hidden'}>
        {children}
      </div>
      <button
        type="button"
        aria-expanded={expanded}
        aria-controls={id}
        className="mt-1 text-sm font-medium text-primary outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring"
        onClick={() => {
          setExpanded(!expanded);
        }}
      >
        {expanded ? __('Hide Content') : __('Show Content')}
      </button>
    </div>
  );
}
