import type { DetailFieldProps, IndexFieldProps } from '../registry';
import type { FieldSchema } from '../types';
import { RelatedName } from './BelongsToField';

/** The value a morphTo field sends: the related type's uriKey, its key and title, and its page when the user may open it. */
export interface MorphToValue {
  type: string;
  id: string | number | null;
  display?: string;
  href?: string | null;
}

export function isMorphToValue(value: unknown): value is MorphToValue {
  return typeof value === 'object' && value !== null && 'type' in value && 'id' in value;
}

function MorphName({ field, value }: { field: FieldSchema; value: unknown }) {
  // The related Resource changes with each record, so the peek reads it from the value.
  return isMorphToValue(value) && value.id !== null ? (
    <RelatedName field={{ ...field, resource: value.type }} value={{ value: value.id, display: value.display ?? '', href: value.href }} />
  ) : (
    <RelatedName field={field} value={null} />
  );
}

export function MorphToIndex({ field, cell }: IndexFieldProps) {
  return <MorphName field={field} value={cell.value} />;
}

export function MorphToDetail({ field }: DetailFieldProps) {
  return <MorphName field={field} value={field.value} />;
}
