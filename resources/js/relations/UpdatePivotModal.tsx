import { toast, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { useId, useState } from 'react';
import { Modal } from '../components/Modal';
import type { RelationFieldSchema } from '../fields/types';
import { initialValues } from '../records/useRecordForm';
import { PivotFields, usePivotFields, writeErrors } from './AttachRelationModal';
import { pivotQuery, useRelationActions } from './useRelationActions';
import type { RelationRow } from './useRelationIndex';

interface UpdatePivotModalProps {
  parent: { uriKey: string; id: string | number };
  field: RelationFieldSchema;
  row: RelationRow;
  onClose: () => void;
}

/** Edit the pivot values of one attached row, loaded with their current values. */
export function UpdatePivotModal({ parent, field, row, onClose }: UpdatePivotModalProps) {
  const { __ } = useTranslation();
  const titleId = useId();
  const actions = useRelationActions(parent.uriKey, parent.id, field.relationship);
  const pivot = usePivotFields(`${actions.base}/${String(row.related_id)}/pivot-fields${pivotQuery(row.pivot_id)}`);
  const fields = pivot.data?.fields ?? [];
  const [typed, setTyped] = useState<Record<string, unknown>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const values = { ...initialValues(fields), ...typed };

  async function update() {
    setBusy(true);
    setErrors({});

    try {
      await actions.updatePivot(row.related_id, row.pivot_id, values);
      toast.success(__(':title was updated.', { title: row.title }));
      onClose();
    } catch (error) {
      setErrors(writeErrors(error, __('Something went wrong. Please try again.')));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Modal labelledBy={titleId} onClose={onClose}>
      <h2 id={titleId} className="text-base font-semibold">
        {__('Edit pivot of :title', { title: row.title })}
      </h2>
      <PivotFields
        fields={fields}
        values={values}
        errors={errors}
        onChange={(attribute, value) => {
          setTyped((current) => ({ ...current, [attribute]: value }));
        }}
      />
      <div className="flex justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          {__('Cancel')}
        </Button>
        <Button
          disabled={busy || pivot.data === undefined}
          onClick={() => {
            void update();
          }}
        >
          {__('Update')}
        </Button>
      </div>
    </Modal>
  );
}
