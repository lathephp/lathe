import type { ReactNode } from 'react';

/** A filter with more than one input: the filter's name labels the group. */
export function FilterFieldset({ legend, children }: { legend: string; children: ReactNode }) {
  return (
    <fieldset className="grid gap-1.5">
      <legend className="mb-1.5 text-sm font-medium">{legend}</legend>
      {children}
    </fieldset>
  );
}
