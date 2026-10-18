import { formatCurrency, formatNumber } from '@lathephp/core';
import { DetailFallbackField, FallbackField, registerField, type DetailFieldProps, type IndexFieldProps } from '../registry';
import { NumberInput } from '../number/NumberInput';
import { TokenInput } from '../number/lazy';
import { deferred } from './deferred';

/** A number, or a decimal string that is one; anything else is not drawn as a number. */
function numeric(value: unknown): value is number | string {
  return (typeof value === 'number' && Number.isFinite(value)) || (typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value)));
}

function IndexNumber({ field, cell }: IndexFieldProps) {
  // A fraction digit cap of 20 keeps 1.23456 whole; Intl's default of 3 would cut it.
  return numeric(cell.value) ? <span className="tabular-nums">{formatNumber(cell.value, { maximumFractionDigits: 20 })}</span> : <FallbackField field={field} cell={cell} />;
}

function DetailNumber({ field }: DetailFieldProps) {
  return numeric(field.value) ? <span className="tabular-nums">{formatNumber(field.value, { maximumFractionDigits: 20 })}</span> : <DetailFallbackField field={field} />;
}

/** The index has only the cell, not the field's currency, so it shows the text the server formatted. */
function IndexCurrency({ cell }: IndexFieldProps) {
  return <span className="tabular-nums">{cell.display}</span>;
}

function DetailCurrency({ field }: DetailFieldProps) {
  const { currency, symbol } = field;

  if (!numeric(field.value) || typeof currency !== 'string') {
    return <DetailFallbackField field={field} />;
  }

  // Intl has no way to swap the glyph, so a symbol the developer set keeps the server's text.
  return <span className="tabular-nums">{typeof symbol === 'string' ? field.display : formatCurrency(field.value, currency)}</span>;
}

function IndexToken({ cell }: IndexFieldProps) {
  return <span className="font-mono">{cell.display}</span>;
}

function DetailToken({ field }: DetailFieldProps) {
  return field.display === '' ? <DetailFallbackField field={field} /> : <span className="font-mono">{field.display}</span>;
}

/** Register the renderers of the number, currency and token fields. */
export function registerNumberFields(): void {
  registerField('number-field', { index: IndexNumber, detail: DetailNumber, form: NumberInput });
  registerField('currency-field', { index: IndexCurrency, detail: DetailCurrency, form: NumberInput });
  registerField('token-field', { index: IndexToken, detail: DetailToken, form: deferred(TokenInput) });
}
