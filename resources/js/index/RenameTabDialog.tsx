import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useId, useState } from 'react';

interface RenameTabDialogProps {
  name: string;
  /** Resolves true once saved, which closes the dialog. */
  onSave: (name: string) => Promise<boolean>;
  onClose: () => void;
}

/** Takes a new name for a saved tab. */
export function RenameTabDialog({ name: current, onSave, onClose }: RenameTabDialogProps) {
  const { __ } = useTranslation();
  const id = useId();
  const [name, setName] = useState(current);
  const [saving, setSaving] = useState(false);

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
          void onSave(name.trim()).then((saved) => {
            setSaving(false);

            if (saved) {
              onClose();
            }
          });
        }}
      >
        <h2 id={`${id}-title`} className="text-base font-semibold">
          {__('Rename view')}
        </h2>
        <div className="space-y-1">
          <label htmlFor={`${id}-name`} className="text-sm font-medium">
            {__('Name')}
          </label>
          <input
            id={`${id}-name`}
            required
            maxLength={100}
            // eslint-disable-next-line jsx-a11y/no-autofocus -- the name is the dialog's only field.
            autoFocus
            value={name}
            className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onChange={(event) => {
              setName(event.target.value);
            }}
          />
        </div>
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
