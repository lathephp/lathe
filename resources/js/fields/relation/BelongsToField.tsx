import { Link } from '@inertiajs/react';
import type { DetailFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { RelationPeek } from './RelationPeek';

/** The value a belongsTo field sends: the related key and title, and its page when the user may open it. */
export interface BelongsToValue {
  value: string | number;
  display: string;
  href?: string | null;
}

export function isBelongsToValue(value: unknown): value is BelongsToValue {
  return typeof value === 'object' && value !== null && 'value' in value && 'display' in value;
}

function RelatedName({ field, value }: { field: FieldSchema; value: unknown }) {
  if (!isBelongsToValue(value)) {
    return <span className="text-muted-foreground">—</span>;
  }

  const name =
    typeof value.href === 'string' ? (
      <Link href={value.href} className="text-primary hover:underline">
        {value.display}
      </Link>
    ) : (
      <span>{value.display}</span>
    );

  return field.peekable === true && typeof field.resource === 'string' ? (
    <RelationPeek uriKey={field.resource} id={value.value}>
      {name}
    </RelationPeek>
  ) : (
    name
  );
}

export function BelongsToIndex({ field, cell }: IndexFieldProps) {
  return <RelatedName field={field} value={cell.value} />;
}

export function BelongsToDetail({ field }: DetailFieldProps) {
  return <RelatedName field={field} value={field.value} />;
}
