import { Link } from '@inertiajs/react';
import type { DetailFieldProps, IndexFieldProps } from '../registry';

/** The value a belongsTo field sends: the related key and title, and its page when the user may open it. */
export interface BelongsToValue {
  value: string | number;
  display: string;
  href?: string | null;
}

export function isBelongsToValue(value: unknown): value is BelongsToValue {
  return typeof value === 'object' && value !== null && 'value' in value && 'display' in value;
}

function RelatedName({ value }: { value: unknown }) {
  if (!isBelongsToValue(value)) {
    return <span className="text-muted-foreground">—</span>;
  }

  if (typeof value.href === 'string') {
    return (
      <Link href={value.href} className="text-primary hover:underline">
        {value.display}
      </Link>
    );
  }

  return <span>{value.display}</span>;
}

export function BelongsToIndex({ cell }: IndexFieldProps) {
  return <RelatedName value={cell.value} />;
}

export function BelongsToDetail({ field }: DetailFieldProps) {
  return <RelatedName value={field.value} />;
}
