import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Modal, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
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
    <Modal role="alertdialog" labelledBy={`${id}-delete`} onClose={onClose}>
      <ModalHeader>
        <ModalTitle id={`${id}-delete`}>{__('Delete :name?', { name })}</ModalTitle>
      </ModalHeader>
      <ModalFooter>
        <Button variant="outline" data-autofocus onClick={onClose}>
          {__('Cancel')}
        </Button>
        <Button variant="destructive-solid" onClick={onDelete}>
          {__('Delete')}
        </Button>
      </ModalFooter>
    </Modal>
  );
}
