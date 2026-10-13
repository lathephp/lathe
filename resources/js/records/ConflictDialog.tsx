import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Modal } from '../components/Modal';

interface ConflictDialogProps {
  onRefresh: () => void;
  onOverride: () => void;
  onClose: () => void;
}

/** Someone saved this record after the form loaded: reload their values or save over them. */
export function ConflictDialog({ onRefresh, onOverride, onClose }: ConflictDialogProps) {
  const { __ } = useTranslation();
  const id = useId();

  return (
    <Modal labelledBy={`${id}-title`} onClose={onClose}>
      <h2 id={`${id}-title`} className="text-base font-semibold">
        {__('This record has changed')}
      </h2>
      <p className="text-sm text-muted-foreground">
        {__('Someone else saved this record after you opened it. Refresh to load their changes, or override to save yours over them.')}
      </p>
      <div className="flex justify-end gap-2">
        <button type="button" className="h-9 rounded-md border border-input px-4 text-sm" onClick={onRefresh}>
          {__('Refresh')}
        </button>
        <button type="button" className="h-9 rounded-md bg-destructive px-4 text-sm font-medium text-white" onClick={onOverride}>
          {__('Override')}
        </button>
      </div>
    </Modal>
  );
}
