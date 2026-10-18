import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Button } from '../components/ui/Button';

interface DeleteTabDialogProps {
  name: string;
  onDelete: () => void;
  onClose: () => void;
}

/** Asks before a saved tab is deleted; Cancel, the safe choice, has the focus. */
export function DeleteTabDialog({ name, onDelete, onClose }: DeleteTabDialogProps) {
  const { __ } = useTranslation();
  const id = useId();

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-scrim p-4 pt-[15vh]">
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby={`${id}-delete`}
        className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg"
      >
        <h2 id={`${id}-delete`} className="text-base font-semibold">
          {__('Delete :name?', { name })}
        </h2>
        <div className="flex justify-end gap-2">
          {/* eslint-disable-next-line jsx-a11y/no-autofocus -- Cancel is the safe choice of a confirm. */}
          <Button variant="outline" autoFocus onClick={onClose}>
            {__('Cancel')}
          </Button>
          <Button onClick={onDelete}>{__('Delete')}</Button>
        </div>
      </div>
    </div>
  );
}
