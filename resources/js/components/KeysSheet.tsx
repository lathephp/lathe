import { useTranslation } from '@lathephp/core';
import { useId } from 'react';
import { Modal, ModalClose, ModalHeader, ModalTitle } from './Modal';

/** The list's keys, grouped. */
export function KeysSheet({ onClose }: { onClose: () => void }) {
  const { __ } = useTranslation();
  const id = useId();
  // Literal __() calls, so the translations check finds every string.
  const groups: { name: string; rows: [keys: string[], does: string][] }[] = [
    {
      name: __('Move'),
      rows: [
        [[__('Arrow keys')], __('Move the cell cursor')],
        [['Home', 'End'], __("Go to the row's first or last cell")],
        [['Page Up', 'Page Down'], __('Move 25 rows')],
      ],
    },
    {
      name: __('Edit'),
      rows: [
        [['Enter', 'F2', __('Double-click')], __('Edit a cell')],
        [['Tab'], __('Save and move right')],
        [['Esc'], __('Cancel')],
      ],
    },
    {
      name: __('Select'),
      rows: [
        [['X'], __('Select the row')],
        [['Shift+Up', 'Shift+Down'], __('Extend the selection')],
        [['Ctrl/Cmd+A'], __('Select the page')],
        [['Ctrl/Cmd+Shift+A'], __('Select all matching')],
        [['Esc'], __('Clear the selection')],
      ],
    },
    { name: __('Undo'), rows: [[['Ctrl/Cmd+Z'], __('Undo the last cell edit')]] },
    {
      name: __('Open'),
      rows: [
        [['Enter'], __('Open the row, on a cell that cannot be edited')],
        [[__('Space')], __('Preview the row beside the list')],
        [['?'], __('Show this sheet')],
      ],
    },
  ];

  return (
    <Modal labelledBy={`${id}-title`} onClose={onClose} className="max-w-[520px]">
      <ModalHeader>
        <ModalTitle id={`${id}-title`}>{__('Keyboard shortcuts')}</ModalTitle>
      </ModalHeader>
      {groups.map((group) => (
        <section key={group.name} className="grid gap-2">
          {/* The style's dialog title at 14 px, so a group stays under the sheet's own title. */}
          <h3 className="cn-dialog-title text-sm">{group.name}</h3>
          <dl className="grid gap-1.5 text-sm">
            {group.rows.map(([keys, does]) => (
              <div key={does} className="flex items-baseline justify-between gap-4">
                <dt className="text-muted-foreground">{does}</dt>
                <dd className="flex flex-wrap justify-end gap-1">
                  {keys.map((key) => (
                    // The kit Kbd's classes: the look is the style's cn-kbd rule.
                    <kbd key={key} className="cn-kbd pointer-events-none inline-flex items-center justify-center select-none">
                      {key}
                    </kbd>
                  ))}
                </dd>
              </div>
            ))}
          </dl>
        </section>
      ))}
      <ModalClose onClick={onClose} />
    </Modal>
  );
}
