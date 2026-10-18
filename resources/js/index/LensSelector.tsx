import { router } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Select } from '../components/ui/Select';
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
    <Select
      aria-label={__('View')}
      value={lens?.uriKey ?? ''}
      options={[{ value: '', label }, ...lenses.map((item) => ({ value: item.uriKey, label: item.name }))]}
      onChange={(key) => {
        router.visit(key === '' ? resourcePath : `${resourcePath}/lens/${key}`);
      }}
    />
  );
}
