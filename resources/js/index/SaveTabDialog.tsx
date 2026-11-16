import { useTranslation } from '@lathephp/core';
import { RadioGroup, RadioGroupItem, Switch } from '@lathephp/ui';
import { useId, useState } from 'react';
import { Modal, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { Button } from '../components/ui/Button';
import { inputClass } from '../components/ui/input';
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
    { value: 'success', label: __('Success'), swatch: 'bg-success' },
    { value: 'danger', label: __('Danger'), swatch: 'bg-destructive' },
    { value: 'warning', label: __('Warning'), swatch: 'bg-warning' },
  ];

  return (
    <Modal labelledBy={`${id}-title`} onClose={onClose}>
      <form
        className="contents"
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
        <ModalHeader>
          <ModalTitle id={`${id}-title`}>{__('Save view')}</ModalTitle>
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
        <label className="flex items-center justify-between gap-3 text-sm font-medium">
          {__('Show count')}
          <Switch
            checked={showCount}
            onCheckedChange={(checked) => {
              setShowCount(checked);
            }}
          />
        </label>
        <fieldset className="space-y-1">
          <legend className="text-sm font-medium">{__('Badge colour')}</legend>
          <RadioGroup
            value={badgeType}
            className="flex gap-3"
            onValueChange={(value) => {
              setBadgeType(value as BadgeColour);
            }}
          >
            {colours.map((colour) => (
              <label key={colour.value} className="flex items-center gap-1.5 text-sm">
                <RadioGroupItem value={colour.value} />
                <span aria-hidden="true" className={cn('size-2.5 rounded-full', colour.swatch)} />
                {colour.label}
              </label>
            ))}
          </RadioGroup>
        </fieldset>
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
