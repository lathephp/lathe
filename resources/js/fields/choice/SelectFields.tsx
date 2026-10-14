import { stringValue, type DetailFieldProps, type IndexFieldProps } from '../registry';
import { labelOf, optionsOf, stringsOf } from './options';

function shown(field: IndexFieldProps['field'], value: unknown): string {
  return field.displayUsingLabels === true ? labelOf(optionsOf(field), stringValue(value)) : stringValue(value);
}

export function SelectIndex({ field, cell }: IndexFieldProps) {
  return shown(field, cell.value);
}

export function SelectDetail({ field }: DetailFieldProps) {
  const text = shown(field, field.value);

  return text === '' ? <span className="text-muted-foreground">—</span> : text;
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
