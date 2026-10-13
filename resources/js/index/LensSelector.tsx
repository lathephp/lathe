import { router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import type { LensEntry } from './types';

interface LensSelectorProps {
  /** The Resource's own URL; a lens lives under it at /lens/{uriKey}. */
  resourcePath: string;
  label: string;
  lens: LensEntry | null;
  lenses: LensEntry[];
}

export function LensSelector({ resourcePath, label, lens, lenses }: LensSelectorProps) {
  const { __ } = useTranslation();

  return (
    <select
      aria-label={__('View')}
      value={lens?.uriKey ?? ''}
      className="h-9 rounded-md border border-border bg-background px-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
      onChange={(event) => {
        const key = event.target.value;
        router.visit(key === '' ? resourcePath : `${resourcePath}/lens/${key}`);
      }}
    >
      <option value="">{label}</option>
      {lenses.map((item) => (
        <option key={item.uriKey} value={item.uriKey}>
          {item.name}
        </option>
      ))}
    </select>
  );
}
