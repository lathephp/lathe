import type { ReactNode } from 'react';

export const fieldClass =
  'h-9 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';

/** A filter with more than one input: the filter's name labels the group. */
export function FilterFieldset({ legend, children }: { legend: string; children: ReactNode }) {
  return (
    <fieldset className="grid gap-1.5">
      <legend className="mb-1.5 text-sm font-medium">{legend}</legend>
      {children}
    </fieldset>
  );
}
