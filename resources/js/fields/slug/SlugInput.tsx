import { useTranslation } from '@lathephp/core';
import { useLayoutEffect, useRef, useState } from 'react';
import { stringValue, type FormFieldProps } from '../registry';

/** The slug of a text with the words joined by the separator, as the server's Str::slug does for plain Latin text. */
export function slugify(text: string, separator: string): string {
  const words = text
    .normalize('NFKD')
    .replace(/\p{M}/gu, '')
    .toLowerCase()
    .split(/[^a-z0-9]+/)
    .filter((word) => word !== '');

  return words.join(separator);
}

const control =
  'lathe-input';

/**
 * A text input that follows the field named in `from` until the user types in it. An update
 * form with the Customize button keeps it locked until the button is pressed.
 */
export default function SlugInput({ field, value, onChange, id, describedBy, invalid, disabled, values }: FormFieldProps) {
  const { __ } = useTranslation();
  const from = typeof field.from === 'string' ? field.from : null;
  const separator = typeof field.separator === 'string' ? field.separator : '-';
  const source = from === null ? '' : stringValue(values?.[from]);
  const follow = field.follow === true && from !== null;
  const [typed, setTyped] = useState(false);
  const [unlocked, setUnlocked] = useState(false);
  const previous = useRef(source);
  const locked = field.locked === true && !unlocked;

  useLayoutEffect(() => {
    if (previous.current === source) {
      return;
    }

    previous.current = source;

    if (follow && !typed) {
      onChange(slugify(source, separator));
    }
  }, [source, follow, typed, separator, onChange]);

  return (
    <div className="flex items-center gap-2">
      <input
        id={id}
        name={field.attribute}
        type="text"
        value={stringValue(value)}
        placeholder={field.placeholder ?? undefined}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled || locked}
        onChange={(event) => {
          setTyped(true);
          onChange(event.target.value);
        }}
        className={control}
      />
      {locked ? (
        <button
          type="button"
          className="h-9 shrink-0 rounded-md border border-input px-3 text-sm"
          onClick={() => {
            setUnlocked(true);
          }}
        >
          {__('Customize')}
        </button>
      ) : null}
    </div>
  );
}
