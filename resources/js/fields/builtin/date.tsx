import { formatDate, formatDateTime } from '@lathephp/core';
import { DetailFallbackField, FallbackField, registerField, type DetailFieldProps, type IndexFieldProps } from '../registry';
import { DateInput, DateTimeInput } from '../date/DateInput';

/** A string that holds a Y-m-d day, which is the only thing formatDate can draw. */
function isDay(value: unknown): value is string {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value);
}

/** A string that holds an ISO 8601 moment, which is the only thing formatDateTime can draw. */
function isMoment(value: unknown): value is string {
  return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value) && !Number.isNaN(Date.parse(value));
}

function IndexDate({ field, cell }: IndexFieldProps) {
  return isDay(cell.value) ? <span>{formatDate(cell.value)}</span> : <FallbackField field={field} cell={cell} />;
}

function DetailDate({ field }: DetailFieldProps) {
  return isDay(field.value) ? <span>{formatDate(field.value)}</span> : <DetailFallbackField field={field} />;
}

function IndexDateTime({ field, cell }: IndexFieldProps) {
  return isMoment(cell.value) ? <span>{formatDateTime(cell.value)}</span> : <FallbackField field={field} cell={cell} />;
}

function DetailDateTime({ field }: DetailFieldProps) {
  return isMoment(field.value) ? <span>{formatDateTime(field.value)}</span> : <DetailFallbackField field={field} />;
}

/** Register the renderers of the date and dateTime fields. */
export function registerDateFields(): void {
  registerField('date-field', { index: IndexDate, detail: DetailDate, form: DateInput });
  registerField('date-time-field', { index: IndexDateTime, detail: DetailDateTime, form: DateTimeInput });
}
