import { toast, useTranslation } from '@lathephp/core';
import { Copy, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { stringValue, type FormFieldProps } from '../registry';
import { inputClass } from './inputClass';

const buttonClass =
  'inline-flex size-9 shrink-0 items-center justify-center rounded-md border border-input bg-background text-muted-foreground outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60';

/** The raw token in a masked input, with a toggle to reveal it and a button to copy it. */
export function TokenInput({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const { __ } = useTranslation();
  const [revealed, setRevealed] = useState(false);
  const token = stringValue(value);

  return (
    <div className="flex gap-2">
      <input
        id={id}
        name={field.attribute}
        type={revealed ? 'text' : 'password'}
        value={token}
        placeholder={field.placeholder ?? undefined}
        autoComplete={field.autocomplete ?? 'off'}
        spellCheck={false}
        aria-describedby={describedBy}
        aria-invalid={invalid || undefined}
        aria-required={field.required === true || undefined}
        disabled={disabled}
        onChange={(event) => {
          onChange(event.target.value);
        }}
        className={`${inputClass} font-mono`}
      />
      <button
        type="button"
        aria-label={revealed ? __('Hide') : __('Show')}
        className={buttonClass}
        onClick={() => {
          setRevealed(!revealed);
        }}
      >
        {revealed ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
      </button>
      <button
        type="button"
        aria-label={__('Copy')}
        className={buttonClass}
        onClick={() => {
          // The Clipboard API is missing on an insecure origin; then there is nothing to copy with.
          void (navigator.clipboard as Clipboard | undefined)?.writeText(token).then(() => {
            toast.success(__('Copied'));
          });
        }}
      >
        <Copy className="size-4" aria-hidden="true" />
      </button>
    </div>
  );
}
