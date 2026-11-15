import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Modal, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { Button } from '../components/ui/Button';

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
    <Modal labelledBy={`${id}-title`} onClose={onClose} className="max-w-[520px]">
      <div className="flex items-start gap-3">
        <span aria-hidden="true" className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-warning-subtle text-warning-subtle-foreground">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="size-4">
            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16M8 16H3v5" />
          </svg>
        </span>
        <ModalHeader>
          <ModalTitle id={`${id}-title`}>{__('This record has changed')}</ModalTitle>
          <ModalDescription>{__('Someone else saved this record after you opened it. Load their version, or save yours over theirs.')}</ModalDescription>
        </ModalHeader>
      </div>
      <ModalFooter>
        <Button variant="destructive" onClick={onOverride}>
          {__('Save mine over theirs')}
        </Button>
        {/* Rightmost as the primary, and focused, so a second Enter never writes over their save. */}
        <Button data-autofocus onClick={onRefresh}>
          {__('Load their version')}
        </Button>
      </ModalFooter>
    </Modal>
  );
}
