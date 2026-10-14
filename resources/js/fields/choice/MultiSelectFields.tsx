import type { DetailFieldProps, IndexFieldProps } from '../registry';
import { stringsOf } from './options';
import { Tags } from './SelectFields';

export function MultiSelectIndex({ field, cell }: IndexFieldProps) {
  return <Tags field={field} value={cell.value} />;
}

export function MultiSelectDetail({ field }: DetailFieldProps) {
  return stringsOf(field.value).length === 0 ? <span className="text-muted-foreground">—</span> : <Tags field={field} value={field.value} />;
}
