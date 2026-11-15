import { useTranslation } from '@lathephp/core';
import { useId, useState } from 'react';
import { Modal, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { Button } from '../components/ui/Button';
import { inputClass } from '../components/ui/input';

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
    <Modal labelledBy={`${id}-title`} onClose={onClose}>
      <form
        className="contents"
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
        <ModalHeader>
          <ModalTitle id={`${id}-title`}>{__('Rename view')}</ModalTitle>
        </ModalHeader>
        <div className="grid gap-2">
          <label htmlFor={`${id}-name`} className="cn-label">
            {__('Name')}
          </label>
          <input
            id={`${id}-name`}
            required
            maxLength={100}
            value={name}
            className={inputClass}
            onChange={(event) => {
              setName(event.target.value);
            }}
          />
        </div>
        <ModalFooter>
          <Button variant="outline" onClick={onClose}>
            {__('Cancel')}
          </Button>
          <Button type="submit" disabled={saving}>
            {__('Save')}
          </Button>
        </ModalFooter>
      </form>
    </Modal>
  );
}
