import { toast, useTranslation } from '@lathephp/core';
import { Copy, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import { stringValue, type FormFieldProps } from '../registry';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/input';

const buttonClass = 'shrink-0 text-muted-foreground';

/** The raw token in a masked input, with a toggle to reveal it and a button to copy it. */
export function TokenInput({ field, value, onChange, id, describedBy, invalid, disabled }: FormFieldProps) {
  const { __ } = useTranslation();
  const [revealed, setRevealed] = useState(false);
  const token = stringValue(value);

  return (
    <div className="flex gap-2">
      <Input
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
        className="font-mono"
      />
      <Button
        variant="outline"
        size="icon"
        aria-label={revealed ? __('Hide') : __('Show')}
        className={buttonClass}
        onClick={() => {
          setRevealed(!revealed);
        }}
      >
        {revealed ? <EyeOff className="size-4" aria-hidden="true" /> : <Eye className="size-4" aria-hidden="true" />}
      </Button>
      <Button
        variant="outline"
        size="icon"
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
      </Button>
    </div>
  );
}
