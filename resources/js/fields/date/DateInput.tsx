import { stringValue, type FormFieldProps } from '../registry';
import { inputClass } from '../number/inputClass';

function attribute(value: unknown): string | undefined {
  return typeof value === 'string' ? value : undefined;
}

/** A Y-m-d value the browser's date input can show; anything else leaves it empty. */
function dayValue(value: unknown): string {
  const text = stringValue(value);

  return /^\d{4}-\d{2}-\d{2}$/.test(text) ? text : '';
}

/** A moment as wall time in the user's timezone. The server converts a UTC value into localValue. */
function wallValue(value: unknown, localValue: unknown): string {
  const text = stringValue(value);

  if (/Z$|[+-]\d{2}:\d{2}$/.test(text)) {
    return typeof localValue === 'string' ? localValue : '';
  }

  return /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(text) ? text : '';
}

interface InputProps extends FormFieldProps {
  type: 'date' | 'datetime-local';
  shown: string;
}

function TemporalInput({ field, onChange, id, describedBy, invalid, disabled, type, shown }: InputProps) {
  return (
    <input
      id={id}
      name={field.attribute}
      type={type}
      value={shown}
      min={attribute(field.min)}
      max={attribute(field.max)}
      aria-describedby={describedBy}
      aria-invalid={invalid || undefined}
      aria-required={field.required === true || undefined}
      disabled={disabled}
      onChange={(event) => {
        onChange(event.target.value);
      }}
      className={inputClass}
    />
  );
}

/** The input of a date field: it sends the Y-m-d text. */
export function DateInput(props: FormFieldProps) {
  return <TemporalInput {...props} type="date" shown={dayValue(props.value)} />;
}

/** The input of a date-time field: it sends the wall time the user typed, which the server reads in their timezone. */
export function DateTimeInput(props: FormFieldProps) {
  return <TemporalInput {...props} type="datetime-local" shown={wallValue(props.value, props.field.localValue)} />;
}
