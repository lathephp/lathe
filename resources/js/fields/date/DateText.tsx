import { formatDate, useTranslation } from '@lathephp/core';
import { CalendarIcon } from 'lucide-react';
import { Suspense, useState } from 'react';
import { DatePicker } from './lazy';
import { parseDate } from './parse';

export interface DateTextProps {
  /** The stored value: '', Y-m-d, or Y-m-dTHH:mm when time is set. */
  value: string;
  time: boolean;
  onChange: (value: string) => void;
  className: string;
  id?: string;
  name?: string;
  label?: string;
  describedBy?: string;
  invalid?: boolean;
  required?: boolean;
  disabled?: boolean;
  min?: string;
  max?: string;
}

/**
 * A text input that reads "Oct 17, 2026" (with ", 23:12" for a date-time) or ISO, and a button
 * that opens the calendar. The calendar's chunk loads on the first open.
 */
export function DateText({ value, time, onChange, className, label, describedBy, invalid, required, disabled, min, max, ...input }: DateTextProps) {
  const { __ } = useTranslation();
  const [draft, setDraft] = useState<string | null>(null);
  const [bad, setBad] = useState(false);
  // Null until the first open, so the calendar's chunk loads only then.
  const [open, setOpen] = useState<boolean | null>(null);
  // A filter's value comes from the URL, so it may not be a day; it shows as it came.
  const shown = /^\d{4}-\d{2}-\d{2}/.test(value) ? formatDate(value) + (time ? `, ${value.slice(11, 16)}` : '') : value;
  const clock = value.slice(11, 16) || '00:00';

  const commit = () => {
    if (draft === null) return;
    const parsed = draft.trim() === '' ? '' : parseDate(draft);

    if (parsed === null || (!time && parsed.length > 10)) {
      // The text goes to the form as typed, so a save sends it and the server's date rule
      // refuses it on the field, rather than keeping the old date under a success toast.
      setBad(true);
      onChange(draft.trim());

      return;
    }

    onChange(parsed === '' || !time ? parsed : `${parsed.slice(0, 10)}T${parsed.slice(11) || clock}`);
    setDraft(null);
  };

  const button = (onClick?: () => void) => (
    <button
      type="button"
      aria-label={__('Open calendar')}
      disabled={disabled}
      onClick={onClick}
      className="absolute inset-y-0 end-0 flex w-9 items-center justify-center rounded-e-md text-muted-foreground outline-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60"
    >
      <CalendarIcon className="size-4" aria-hidden />
    </button>
  );

  return (
    <div className="relative min-w-0 flex-1">
      <input
        {...input}
        type="text"
        value={draft ?? shown}
        aria-label={label}
        aria-describedby={describedBy}
        aria-invalid={invalid || bad || undefined}
        aria-required={required || undefined}
        disabled={disabled}
        onChange={(event) => {
          setDraft(event.target.value);
          setBad(false);
        }}
        onBlur={commit}
        onKeyDown={(event) => {
          if (event.key === 'Enter') {
            // Enter stores the typed date instead of submitting the form with the old one.
            event.preventDefault();
            commit();
          }
        }}
        className={`${className} pe-9`}
      />
      {open === null ? (
        button(() => {
          setOpen(true);
        })
      ) : (
        <Suspense fallback={button()}>
          <DatePicker
            open={open}
            onOpenChange={setOpen}
            trigger={button()}
            value={value}
            time={time}
            min={min}
            max={max}
            onPick={(day) => {
              onChange(time ? `${day}T${clock}` : day);
              setDraft(null);
              setBad(false);
              if (!time) setOpen(false);
            }}
            onTime={(hhmm) => {
              if (value !== '') onChange(`${value.slice(0, 10)}T${hhmm}`);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
