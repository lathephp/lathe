import { Badge, isBadgeTone } from '../../components/ui/Badge';
import type { FieldSchema } from '../types';
import { stringValue, type DetailFieldProps, type IndexFieldProps } from '../registry';
import { labelOf, optionsOf, stringsOf } from './options';

function shown(field: IndexFieldProps['field'], value: unknown): string {
  return field.displayUsingLabels === true || badgesOf(field) !== null ? labelOf(optionsOf(field), stringValue(value)) : stringValue(value);
}

export function badgesOf(field: FieldSchema): Record<string, unknown> | null {
  return typeof field.badges === 'object' && field.badges !== null ? (field.badges as Record<string, unknown>) : null;
}

/** A select's value as a badge in the tone badges() gave it, or null when the field has no badges. */
export function SelectBadge({ field, value }: { field: FieldSchema; value: unknown }) {
  const badges = badgesOf(field);
  const text = shown(field, value);

  if (badges === null || text === '') {
    return null;
  }

  const tone = badges[stringValue(value)];

  return (
    <Badge tone={isBadgeTone(tone) ? tone : 'neutral'} dot>
      {text}
    </Badge>
  );
}

export function SelectIndex({ field, cell }: IndexFieldProps) {
  return badgesOf(field) === null ? shown(field, cell.value) : <SelectBadge field={field} value={cell.value} />;
}

export function SelectDetail({ field }: DetailFieldProps) {
  const text = shown(field, field.value);

  if (text === '') {
    return <span className="text-muted-foreground">—</span>;
  }

  return badgesOf(field) === null ? text : <SelectBadge field={field} value={field.value} />;
}

/** Tags for a list of values, each with the label of its option. */
export function Tags({ field, value }: { field: IndexFieldProps['field']; value: unknown }) {
  const options = optionsOf(field);

  return (
    <span className="inline-flex flex-wrap gap-1">
      {stringsOf(value).map((item) => (
        <span key={item} className="rounded-md bg-muted px-2 py-0.5 text-xs">
          {labelOf(options, item)}
        </span>
      ))}
    </span>
  );
}
