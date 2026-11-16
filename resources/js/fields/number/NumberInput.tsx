import { stringValue, type FormFieldProps } from '../registry';
import { Input } from '../../components/ui/input';

function attribute(value: unknown): string | undefined {
  return typeof value === 'number' || typeof value === 'string' ? String(value) : undefined;
}

/** The input of a number or currency field: min, max and step go to the browser as the server sent them. */
export function NumberInput({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  return (
    <Input
      id={id}
      name={field.attribute}
      type="number"
      inputMode="decimal"
      value={stringValue(value)}
      min={attribute(field.min)}
      max={attribute(field.max)}
      step={attribute(field.step) ?? 'any'}
      placeholder={field.placeholder ?? undefined}
      autoComplete={field.autocomplete ?? undefined}
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      aria-required={field.required === true || undefined}
      disabled={disabled}
      onChange={(event) => {
        onChange(event.target.value);
      }}
      className="tabular-nums"
    />
  );
}
