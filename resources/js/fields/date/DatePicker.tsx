import { useTranslation } from '@lathephp/core';
import { Calendar } from '@lathephp/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@lathephp/ui/popover';
import { useState, type ReactElement } from 'react';
import { inputClass } from '../number/inputClass';
import { pad } from './parse';

interface DatePickerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** The field's calendar button, which the popover opens from and returns focus to. */
  trigger: ReactElement;
  value: string;
  time: boolean;
  min?: string;
  max?: string;
  /** Takes the picked day as Y-m-d. */
  onPick: (day: string) => void;
  /** Takes a typed time as HH:mm. */
  onTime: (time: string) => void;
}

const clock = /^([01]?\d|2[0-3]):([0-5]\d)$/;

/** A Y-m-d prefix as local midnight, the day the calendar draws. */
function day(text: string): Date {
  const [year = 0, month = 1, date = 1] = text.slice(0, 10).split('-').map(Number);

  return new Date(year, month - 1, date);
}

/** The calendar of a date field in a popover, with an HH:mm input under it for a date-time. */
export function DatePicker({ open, onOpenChange, trigger, value, time, min, max, onPick, onTime }: DatePickerProps) {
  const { __ } = useTranslation();
  const [draft, setDraft] = useState<string | null>(null);
  const selected = value === '' ? undefined : day(value);

  const commit = () => {
    if (draft === null) return;
    const found = clock.exec(draft.trim());

    if (found) {
      onTime(`${pad(Number(found[1]))}:${found[2] ?? '00'}`);
      setDraft(null);
    }
  };

  return (
    <Popover open={open} onOpenChange={onOpenChange}>
      <PopoverTrigger render={trigger} />
      <PopoverContent align="end" className="w-auto p-2">
        <Calendar
          mode="single"
          required
          selected={selected}
          defaultMonth={selected}
          disabled={[...(min ? [{ before: day(min) }] : []), ...(max ? [{ after: day(max) }] : [])]}
          onSelect={(picked) => {
            onPick(`${String(picked.getFullYear())}-${pad(picked.getMonth() + 1)}-${pad(picked.getDate())}`);
          }}
        />
        {time && (
          <input
            type="text"
            inputMode="numeric"
            aria-label={__('Time')}
            aria-invalid={(draft !== null && !clock.test(draft.trim())) || undefined}
            value={draft ?? value.slice(11, 16)}
            onChange={(event) => {
              setDraft(event.target.value);
            }}
            onBlur={commit}
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault();
                commit();
              }
            }}
            className={`${inputClass} mt-2`}
          />
        )}
      </PopoverContent>
    </Popover>
  );
}
