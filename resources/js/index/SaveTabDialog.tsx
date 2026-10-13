import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useId, useState } from 'react';
import { cn } from '../lib/cn';

type BadgeColour = 'success' | 'danger' | 'warning';

export interface SaveTabValues {
  name: string;
  showCount: boolean;
  badgeType: BadgeColour;
}

interface SaveTabDialogProps {
  /** Resolves true once saved, which closes the dialog. */
  onSave: (values: SaveTabValues) => Promise<boolean>;
  onClose: () => void;
}

/** Names the current view and saves it as a tab. */
export function SaveTabDialog({ onSave, onClose }: SaveTabDialogProps) {
  const { __ } = useTranslation();
  const id = useId();
  const [name, setName] = useState('');
  const [showCount, setShowCount] = useState(false);
  const [badgeType, setBadgeType] = useState<BadgeColour>('success');
  const [saving, setSaving] = useState(false);
  const colours: { value: BadgeColour; label: string; swatch: string }[] = [
    { value: 'success', label: __('Success'), swatch: 'bg-emerald-500' },
    { value: 'danger', label: __('Danger'), swatch: 'bg-red-500' },
    { value: 'warning', label: __('Warning'), swatch: 'bg-amber-500' },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[15vh]">
      <button type="button" tabIndex={-1} aria-label={__('Close')} className="absolute inset-0 bg-black/50" onClick={onClose} />
      {/* Escape closes the dialog wherever focus is inside it. */}
      {/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions */}
      <form
        role="dialog"
        aria-modal="true"
        aria-labelledby={`${id}-title`}
        className="relative w-full max-w-sm space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg"
        onKeyDown={(event) => {
          if (event.key === 'Escape') {
            onClose();
          }
        }}
        onSubmit={(event) => {
          event.preventDefault();
          setSaving(true);
          void onSave({ name: name.trim(), showCount, badgeType }).then((saved) => {
            setSaving(false);

            if (saved) {
              onClose();
            }
          });
        }}
      >
        <h2 id={`${id}-title`} className="text-base font-semibold">
          {__('Save view')}
        </h2>
        <div className="space-y-1">
          <label htmlFor={`${id}-name`} className="text-sm font-medium">
            {__('Name')}
          </label>
          <input
            id={`${id}-name`}
            required
            maxLength={100}
            // eslint-disable-next-line jsx-a11y/no-autofocus -- the name is the dialog's only required field.
            autoFocus
            value={name}
            className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onChange={(event) => {
              setName(event.target.value);
            }}
          />
        </div>
        <label className="flex items-center justify-between gap-3 text-sm font-medium">
          {__('Show count')}
          <button
            type="button"
            role="switch"
            aria-checked={showCount}
            className={cn(
              'relative h-5 w-9 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring',
              showCount ? 'bg-primary' : 'bg-muted',
            )}
            onClick={() => {
              setShowCount(!showCount);
            }}
          >
            <span
              aria-hidden="true"
              className={cn('absolute top-0.5 size-4 rounded-full bg-background transition-[inset-inline-start]', showCount ? 'start-4.5' : 'start-0.5')}
            />
          </button>
        </label>
        <fieldset className="space-y-1">
          <legend className="text-sm font-medium">{__('Badge colour')}</legend>
          <div className="flex gap-3">
            {colours.map((colour) => (
              <label key={colour.value} className="flex items-center gap-1.5 text-sm">
                <input
                  type="radio"
                  name={`${id}-badge`}
                  value={colour.value}
                  checked={badgeType === colour.value}
                  onChange={() => {
                    setBadgeType(colour.value);
                  }}
                />
                <span aria-hidden="true" className={cn('size-2.5 rounded-full', colour.swatch)} />
                {colour.label}
              </label>
            ))}
          </div>
        </fieldset>
        <div className="flex justify-end gap-2">
          <Button variant="outline" onClick={onClose}>
            {__('Cancel')}
          </Button>
          <Button type="submit" disabled={saving}>
            {__('Save')}
          </Button>
        </div>
      </form>
    </div>
  );
}
