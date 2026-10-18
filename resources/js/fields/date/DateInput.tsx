import { stringValue, type FormFieldProps } from '../registry';
import { inputClass } from '../number/inputClass';
import { DateText } from './DateText';

function attribute(value: unknown): string | undefined {
  return typeof value === 'string' ? value : undefined;
}

/** A Y-m-d value; anything else leaves the input empty. */
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

function TemporalInput({ field, onChange, id, describedBy, invalid, disabled, time, stored }: FormFieldProps & { time: boolean; stored: string }) {
  return (
    <DateText
      id={id}
      name={field.attribute}
      value={stored}
      time={time}
      min={attribute(field.min)}
      max={attribute(field.max)}
      describedBy={describedBy}
      invalid={invalid}
      required={field.required === true}
      disabled={disabled}
      onChange={onChange}
      className={inputClass}
    />
  );
}

/** The input of a date field: it sends the Y-m-d text. */
export function DateInput(props: FormFieldProps) {
  return <TemporalInput {...props} time={false} stored={dayValue(props.value)} />;
}

/** The input of a date-time field: it sends the wall time the user typed, which the server reads in their timezone. */
export function DateTimeInput(props: FormFieldProps) {
  return <TemporalInput {...props} time stored={wallValue(props.value, props.field.localValue)} />;
}
